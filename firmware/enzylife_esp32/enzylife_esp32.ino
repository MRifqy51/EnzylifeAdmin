#include <WiFi.h>
#include <WiFiClientSecure.h>    
#include <PubSubClient.h>
#include <DHT.h>
#include <OneWire.h>             
#include <DallasTemperature.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <RTClib.h>
#include <FS.h>
#include <SD.h>
#include <SPI.h>
#include "secrets.h"

// ===== CONFIGURATIONS & PINS =====
#define DHTPIN 25
#define DHTTYPE DHT22
#define MQ135_PIN 36
#define PH_PIN 39
#define DS18B20_PIN 32 
#define SD_CS_PIN 5 

// Konfigurasi WiFi & MQTT Sesuai Jaringanmu
const char* ssid = SECRET_SSID;
const char* wifi_password = SECRET_PASS;
const char* mqtt_server = SECRET_MQTT_SERVER;
const int mqtt_port = 8883;
const char* mqtt_user = SECRET_MQTT_USER;
const char* mqtt_password = SECRET_MQTT_PASS;
const char* mqtt_topic = "enzylife/device01";
const char* mqtt_cmd_topic = "enzylife/device01/cmd"; 

// Instansiasi Library
DHT dht(DHTPIN, DHTTYPE);
OneWire oneWire(DS18B20_PIN);
DallasTemperature ds18b20(&oneWire);
LiquidCrystal_I2C lcd(0x27, 16, 2);
RTC_DS3231 rtc;
WiFiClientSecure espClient;
PubSubClient client(espClient);

// Variabel Global Sensor & Status
float g_humidity = 0.0;
float g_airTemperature = 0.0;
float g_liquidTemperature = 0.0;
int g_gasRaw = 0;
float g_phValue = 0.0;
bool g_isDisconnected = false;

// State Machine SD Card
enum SDCardState {
    SD_UNMOUNTED,
    SD_READY,
    SD_ERROR,
    SD_EJECTED
};
SDCardState currentSDState = SD_UNMOUNTED;
bool sdAvailable = false; 

bool rtcAvailable = false; 

// Non-Blocking Timers
unsigned long lastSend = 0;
const unsigned long sendInterval = 5000; 

unsigned long lastSDCheck = 0;
const unsigned long sdCheckInterval = 10000; 

unsigned long lastWiFiReconnectAttempt = 0;
const unsigned long wifiReconnectInterval = 10000; 

unsigned long lastMQTTReconnectAttempt = 0;
const unsigned long mqttReconnectInterval = 10000; 

// Prototipe Fungsi
void mountSD();
void unmountSD();
void checkSDCard();
void createCSVIfNeeded();
void writeLogToSD(String timestamp);
void callback(char* topic, byte* payload, unsigned int length);
void connectMQTTNonBlocking(); 
void handleWiFiReconnectNonBlocking(); 
void updateLCD();
String getTimestamp();
void readPublishAndLogSensor();

void mountSD() {
    Serial.print("[SD] Menginisialisasi SD Card... ");
    if (!SD.begin(SD_CS_PIN)) {
        Serial.println("GAGAL! Berjalan dalam mode tanpa SD Card.");
        currentSDState = SD_ERROR;
        sdAvailable = false;
    } else {
        Serial.println("BERHASIL! Penyimpanan lokal siap.");
        currentSDState = SD_READY;
        sdAvailable = true;
        createCSVIfNeeded();
    }
}

void unmountSD() {
    Serial.println("[SD] Melakukan unmount berkas dan menonaktifkan pin SPI...");
    SD.end();
    currentSDState = SD_EJECTED;
    sdAvailable = false;
    Serial.println("[SD] Unmounted & Ejected Safely.");
}

void createCSVIfNeeded() {
    if (currentSDState != SD_READY) return;

    if (!SD.exists("/enzylife_log.csv")) {
        File rootFile = SD.open("/enzylife_log.csv", FILE_WRITE);
        if (rootFile) {
            rootFile.println("Timestamp,Air_Temp,Liquid_Temp,Humidity,Gas_Raw,pH_Value");
            rootFile.close();
            Serial.println("[SD] Berkas csv baru berhasil dibuat.");
        } else {
            Serial.println("[SD] File Open Failed: Gagal membuat berkas csv utama.");
            currentSDState = SD_ERROR;
            sdAvailable = false;
        }
    }
}

// ===== SOLUSI OTOMATISASI FIX REKONEKSI SD CARD =====
void checkSDCard() {
    if (millis() - lastSDCheck >= sdCheckInterval) {
        lastSDCheck = millis();

        if (currentSDState == SD_READY) {
            File testFile = SD.open("/enzylife_log.csv", FILE_READ);
            if (!testFile) {
                Serial.println("[SD] WARNING: SD Card terdeteksi dicabut fisik!");
                currentSDState = SD_UNMOUNTED;
                sdAvailable = false;
            } else {
                testFile.close(); 
            }
        } 
        // Jika statusnya UNMOUNTED, ERROR, atau habis di-EJECT lewat tombol web
        else {
            Serial.println("[SD] Auto-Detect: Mencoba menginisialisasi ulang hardware...");
            
            SD.end();                      // Bersihkan sisa register SPI lama
            digitalWrite(SD_CS_PIN, HIGH); // Buang sisa logika tegangan statis pada pin CS
            delay(150);                    // Beri jeda singkat 150ms agar chip memori stabil
            
            if (SD.begin(SD_CS_PIN)) {
                currentSDState = SD_READY;
                sdAvailable = true;
                Serial.println("[SD] Auto Mount Success: Kartu baru terdeteksi dan pulih otomatis!");
                createCSVIfNeeded();
            } else {
                Serial.println("[SD] Kartu belum dimasukkan kembali atau tidak merespon.");
            }
        }
    }
}

void writeLogToSD(String timestamp) {
    if (currentSDState != SD_READY) {
        return;
    }

    File logFile = SD.open("/enzylife_log.csv", FILE_APPEND);
    if (logFile) {
        logFile.print(timestamp + ",");
        logFile.print(String(g_airTemperature, 2) + ",");
        if (g_isDisconnected) logFile.print("null,"); else logFile.print(String(g_liquidTemperature, 2) + ",");
        logFile.print(String(g_humidity, 2) + ",");
        logFile.print(String(g_gasRaw) + ",");
        logFile.println(String(g_phValue, 2));
        logFile.close();
        Serial.println("[LOCAL] Sukses mencatat data ke SD Card (.csv)");
    } else {
        Serial.println("[LOCAL] Gagal membuka berkas log di SD Card.");
        currentSDState = SD_ERROR;
        sdAvailable = false;
    }
}

// ===== MQTT CALLBACK =====
void callback(char* topic, byte* payload, unsigned int length) { 
    String message = "";
    message.reserve(length + 1); 
    for (unsigned int i = 0; i < length; i++) {
        message += (char)payload[i];
    }
    Serial.println("\n[MQTT CMD] Perintah Masuk di Topik: " + String(topic));
    Serial.println("[MQTT CMD] Isi Perintah: " + message);
    
    if (String(topic) == mqtt_cmd_topic) {
        // Menerima perintah Eject dari tombol satu-satunya di website kamu
        if (message == "EJECT_SD") {
            if (currentSDState == SD_READY) {
                unmountSD();
                Serial.println("[SYSTEM] MicroSD Card sukses di-eject! Aman untuk dicabut.");
            } else {
                Serial.println("[SYSTEM] Proses Eject diabaikan: SD Card sudah tidak aktif.");
            }
        }
    }
}

void connectMQTTNonBlocking() {
    if (WiFi.status() != WL_CONNECTED) return; 
    
    if (millis() - lastMQTTReconnectAttempt >= mqttReconnectInterval) {
        lastMQTTReconnectAttempt = millis();
        
        Serial.print("Connecting to HiveMQ... ");
        String clientId = "ESP32-EnzyLife-";
        clientId += String(random(0xffff), HEX);
        
        if (client.connect(clientId.c_str(), mqtt_user, mqtt_password)) {
            Serial.println("Connected");
            client.subscribe(mqtt_cmd_topic);
        } else {
            Serial.print("Failed, rc=");
            Serial.println(client.state());
        }
    }
}

void handleWiFiReconnectNonBlocking() {
    if (WiFi.status() != WL_CONNECTED) {
        if (millis() - lastWiFiReconnectAttempt >= wifiReconnectInterval) {
            lastWiFiReconnectAttempt = millis();
            Serial.print("Connecting WiFi: ");
            Serial.println(ssid);
            WiFi.disconnect();
            WiFi.begin(ssid, wifi_password);
        }
    }
}

void setup() {
    Serial.begin(115200);
    delay(2000);
    
    lcd.init();
    lcd.backlight();
    lcd.setCursor(0, 0);
    lcd.print("EnzyLife System");
    delay(2000);

    Serial.println("\n=================================");
    Serial.println("   EnzyLife ESP32 FULL SYSTEM");
    Serial.println("=================================");
    
    dht.begin();
    ds18b20.begin();
    
    if (!rtc.begin()) {
        Serial.println("[ERROR] Modul RTC DS3231 Tidak Ditemukan!");
        rtcAvailable = false;
    } else {
        Serial.println("[OK] Modul RTC DS3231 Terdeteksi.");
        rtcAvailable = true;
        rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    }

    WiFi.begin(ssid, wifi_password);
    unsigned long startTimeout = millis();
    Serial.print("Connecting WiFi: ");
    Serial.println(ssid);
    while (WiFi.status() != WL_CONNECTED && millis() - startTimeout < 10000) { 
        delay(500);
        Serial.print(".");
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi Connected");
    } else {
        Serial.println("\n[WiFi] Timeout awal terlewati, beralih ke background monitoring.");
    }

    Serial.print("Jumlah DS18B20 Terdeteksi = ");
    Serial.println(ds18b20.getDeviceCount());

    espClient.setInsecure(); 
    client.setServer(mqtt_server, mqtt_port);
    client.setCallback(callback);

    if (WiFi.status() == WL_CONNECTED) {
        connectMQTTNonBlocking();
    }

    mountSD();
}

void loop() {
    handleWiFiReconnectNonBlocking();
    
    if (WiFi.status() == WL_CONNECTED && !client.connected()) {
        connectMQTTNonBlocking();
    }
    
    client.loop();
    updateLCD();
    checkSDCard(); // Fungsi pemantau otomatis latar belakang berjalan di sini

    if (millis() - lastSend >= sendInterval) {
        lastSend = millis();
        readPublishAndLogSensor();
    }
}

void updateLCD() {
    static unsigned long lastChange = 0;
    static int page = 0;

    if (millis() - lastChange > 3000) {
        lastChange = millis();
        page++;
        if (page > 2) page = 0;
        lcd.clear();
    }

    lcd.setCursor(0, 0);
    if (page == 0) {
        lcd.print(getTimestamp().substring(11, 19)); 
        lcd.setCursor(0, 1);
        lcd.print("M:"); lcd.print(client.connected() ? "ON" : "OFF");
        lcd.print(" SD:"); lcd.print(sdAvailable ? "OK" : "NO");
    } else if (page == 1) {
        lcd.print("Udr:"); lcd.print(g_airTemperature, 1); lcd.print("C");
        lcd.setCursor(0, 1);
        lcd.print("Cai: "); 
        if (g_isDisconnected) lcd.print("---"); else { lcd.print(g_liquidTemperature, 1); lcd.print("C"); }
    } else {
        lcd.print("H:"); lcd.print(g_humidity, 0); lcd.print("% G:"); lcd.print(g_gasRaw);
        lcd.setCursor(0, 1);
        lcd.print("pH:"); lcd.print(g_phValue, 1);
    }
}

String getTimestamp() {
    if (!rtcAvailable) {
        return "2026-06-30 00:00:00"; 
    }
    DateTime now = rtc.now();
    char buffer[20];
    sprintf(buffer, "%04d-%02d-%02d %02d:%02d:%02d", now.year(), now.month(), now.day(), now.hour(), now.minute(), now.second());
    return String(buffer);
}

void readPublishAndLogSensor() {
    String timestamp = getTimestamp();

    g_humidity = dht.readHumidity();
    g_airTemperature = dht.readTemperature();
    if (isnan(g_humidity) || isnan(g_airTemperature)) {
        g_humidity = 0; g_airTemperature = 0;
    }

    g_gasRaw = analogRead(MQ135_PIN);

    int phRaw = 0;
    for (int i = 0; i < 15; i++) {
        phRaw += analogRead(PH_PIN);
        delay(10); 
    }
    phRaw /= 15;
    float phVoltage = (float)phRaw * (3.3 / 4095.0);
    g_phValue = 6.86 - 5.566 * (phVoltage - 1.562);
    if (g_phValue < 0.0) g_phValue = 0.0;
    if (g_phValue > 14.0) g_phValue = 14.0;

    ds18b20.requestTemperatures();
    float rawLiquidTemp = ds18b20.getTempCByIndex(0);
    g_isDisconnected = (rawLiquidTemp == DEVICE_DISCONNECTED_C || rawLiquidTemp == 85.00 || rawLiquidTemp == -127.00);

    if (g_isDisconnected) {
        g_liquidTemperature = 0.00;
    } else {
        g_liquidTemperature = rawLiquidTemp;
    }

    Serial.println("\n========== DATA MONITORING ==========");
    Serial.println("Waktu System : " + timestamp);
    Serial.printf("Suhu Udara   : %.2f °C\n", g_airTemperature);
    Serial.print("Suhu Cairan  : ");
    if (g_isDisconnected) Serial.println("TIDAK TERDETEKSI"); else Serial.printf("%.2f °C\n", g_liquidTemperature);
    Serial.printf("Kelembapan   : %.2f %%\n", g_humidity);
    Serial.printf("Gas MQ135 Raw : %d\n", g_gasRaw);
    Serial.printf("Nilai pH    : %.2f\n", g_phValue);
    Serial.println("-------------------------------------");

    writeLogToSD(timestamp);

    String payload = "{";
    payload.reserve(256); 
    payload += "\"timestamp\":\"" + timestamp + "\",";
    payload += "\"device_id\":\"device01\","; 
    payload += "\"sd_available\":" + String(sdAvailable ? "true" : "false") + ",";
    payload += "\"temperature\":" + String(g_airTemperature, 2) + ",";
    if (g_isDisconnected) {
        payload += "\"liquid_temperature\":null,";
    } else {
        payload += "\"liquid_temperature\":" + String(g_liquidTemperature, 2) + ",";
    }
    payload += "\"humidity\":" + String(g_humidity, 2) + ",";
    payload += "\"gas\":" + String(g_gasRaw) + ",";
    payload += "\"ph\":" + String(g_phValue, 2);
    payload += "}";

    if (client.connected()) {
        if (client.publish(mqtt_topic, payload.c_str())) {
            Serial.println("[CLOUD] Sukses mengirim data ke HiveMQ Cloud.");
        } else {
            Serial.println("[CLOUD] Gagal mengirim data via MQTT.");
        }
    } else {
        Serial.println("[CLOUD] Publish Skipped: Broker sedang offline.");
    }
    Serial.println("=====================================");
}