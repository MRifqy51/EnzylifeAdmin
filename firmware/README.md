Folder ini berisi kode program untuk mikrokontroler ESP32 pada sistem monitoring EnzyLife.

## Panduan Upload via VS Code Terminal (Arduino CLI)

Gunakan perintah di bawah ini untuk mengompilasi dan mengunggah langsung dari terminal VS Code:

```bash
# Pastikan ESP32 tercolok ke port USB (Ganti COM yang sesuai dengan port yang terdeteksi)
arduino-cli compile --upload -p COM3 -b esp32:esp32:esp32 firmware/enzylife_esp32