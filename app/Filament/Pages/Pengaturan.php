<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\MqttPublisherService;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class Pengaturan extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.pengaturan';

    public ?array $data = [];

    // ==========================================
    // 1. INITIALIZATION
    // ==========================================
    public function mount(): void
    {
        $user = Auth::user();
        $setting = $this->getSettingProperty();

        $this->form->fill([

            // ACCOUNT
            'full_name' => $user?->name,
            'email_address' => $user?->email,

            // THRESHOLD
            'ph_min' => $setting->ph_min,
            'ph_max' => $setting->ph_max,

            'temperature_min' => $setting->temperature_min,
            'temperature_max' => $setting->temperature_max,

            'liquid_temperature_min' =>
                $setting->liquid_temperature_min,

            'liquid_temperature_max' =>
                $setting->liquid_temperature_max,

            'gas_min' => $setting->gas_min,
            'gas_max' => $setting->gas_max,

            'humidity_min' => $setting->humidity_min,
            'humidity_max' => $setting->humidity_max,

            // INTERVAL
            'collection_interval' =>
                $setting->collection_interval,

            // WHATSAPP
            'whatsapp_number' =>
                $setting->whatsapp_number
                ?: config('services.fonnte.admin_phone'),

            // PASSWORD
            'current_password' => null,
            'new_password' => null,
            'confirm_password' => null,
        ]);
    }

    // ==========================================
    // 2. FORM
    // ==========================================
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([

                Tabs::make('Pengaturan Tabs')
                    ->tabs([

                        // ==========================
                        // TAB: THRESHOLDS
                        // ==========================
                        Tab::make('Ambang Batas Sensor')
                            ->icon(
                                'heroicon-o-adjustments-horizontal'
                            )
                            ->schema([

                                Section::make('Batas Ambang Nilai Sensor')
                                    ->icon(
                                        'heroicon-o-adjustments-horizontal'
                                    )
                                    ->schema([

                                        $this->rangeField(
                                            'ph',
                                            'pH'
                                        ),

                                        $this->rangeField(
                                            'temperature',
                                            'Air Temp'
                                        ),

                                        $this->rangeField(
                                            'liquid_temperature',
                                            'Liquid Temp'
                                        ),

                                        // MQ-135 masih Raw ADC.
                                        // Threshold gas tidak dipakai
                                        // oleh SensorObserver saat ini.
                                        $this->rangeField(
                                            'gas',
                                            'Gas (Raw ADC)'
                                        ),

                                        $this->rangeField(
                                            'humidity',
                                            'Humidity (%)'
                                        ),
                                    ]),

                                Section::make('Interval Pengambilan Data')
                                    ->schema([

                                        TextInput::make(
                                            'collection_interval'
                                        )
                                            ->label(
                                                'Interval Waktu (Detik)'
                                            )
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(3600)
                                            ->required()
                                            ->helperText(
                                                'Interval 5–3600 detik. Jika nilai berubah, '
                                                . 'EnzyLife akan mengirim konfigurasi '
                                                . 'melalui MQTT ke ESP32.'
                                            ),
                                    ]),
                            ]),

                        // ==========================
                        // TAB: ACCOUNT
                        // ==========================
                        Tab::make('Akun Pengguna')
                            ->icon('heroicon-o-user')
                            ->schema([

                                // ======================
                                // PROFIL ADMIN
                                // ======================
                                Section::make('Informasi Akun')
                                    ->icon('heroicon-o-user')
                                    ->schema([

                                        TextInput::make('full_name')
                                            ->label('Nama Lengkap')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make(
                                            'email_address'
                                        )
                                            ->label('Alamat Email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),

                                // ======================
                                // WHATSAPP SETTINGS
                                // ======================
                                Section::make('WhatsApp Alert')
                                    ->icon(
                                        'heroicon-o-chat-bubble-left-right'
                                    )
                                    ->description(
                                        'Atur nomor penerima '
                                        . 'notifikasi otomatis EnzyLife.'
                                    )
                                    ->schema([

                                        TextInput::make(
                                            'whatsapp_number'
                                        )
                                            ->label(
                                                'Nomor WhatsApp Penerima'
                                            )
                                            ->tel()
                                            ->placeholder(
                                                '6281234567890'
                                            )
                                            ->helperText(
                                                'Gunakan format 62 '
                                                . 'tanpa tanda + '
                                                . 'atau spasi.'
                                            )
                                            ->regex(
                                                '/^62[0-9]{8,13}$/'
                                            )
                                            ->maxLength(15)
                                            ->required()
                                            ->validationMessages([
                                                'required' =>
                                                    'Nomor WhatsApp '
                                                    . 'wajib diisi.',

                                                'regex' =>
                                                    'Nomor harus diawali '
                                                    . '62 dan hanya '
                                                    . 'berisi angka.',
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                // ======================
                                // CHANGE PASSWORD
                                // ======================
                                Section::make('Ubah Kata Sandi')
                                    ->description(
                                        'Kosongkan seluruh kolom '
                                        . 'jika tidak ingin '
                                        . 'mengubah password.'
                                    )
                                    ->schema([

                                        TextInput::make(
                                            'current_password'
                                        )
                                            ->label(
                                                'Kata Sandi Saat Ini'
                                            )
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false),

                                        TextInput::make(
                                            'new_password'
                                        )
                                            ->label(
                                                'New Password'
                                            )
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false),

                                        TextInput::make(
                                            'confirm_password'
                                        )
                                            ->label(
                                                'Konfirmasi Kata Sandi Baru'
                                            )
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    // ==========================================
    // 3. THRESHOLD HELPER
    // ==========================================
    protected function rangeField(
        string $key,
        string $label
    ): Grid {
        return Grid::make(2)
            ->schema([

                TextInput::make("{$key}_min")
                    ->label("{$label} Min")
                    ->numeric()
                    ->inputMode('decimal')
                    ->required(),

                TextInput::make("{$key}_max")
                    ->label("{$label} Max")
                    ->numeric()
                    ->inputMode('decimal')
                    ->gte("{$key}_min")
                    ->required(),
            ]);
    }

    // ==========================================
    // 4. SAVE ALL SETTINGS
    // ==========================================
    public function save(): void
    {
        $user = Auth::user();

        if (!$user) {
            Notification::make()
                ->title('Sesi login tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        // ======================================
        // AMBIL INPUT PASSWORD SEBELUM
        // FORM DI-DEHYDRATE
        // ======================================
        $currentPassword = (string) (
            $this->data['current_password'] ?? ''
        );

        $newPassword = (string) (
            $this->data['new_password'] ?? ''
        );

        $confirmPassword = (string) (
            $this->data['confirm_password'] ?? ''
        );

        // ======================================
        // PERIKSA APAKAH ADMIN INGIN
        // MENGUBAH PASSWORD
        // ======================================
        $wantsPasswordChange =
            $currentPassword !== '' ||
            $newPassword !== '' ||
            $confirmPassword !== '';

        // ======================================
        // VALIDASI PASSWORD
        // ======================================
        if ($wantsPasswordChange) {

            Validator::make(
                [
                    'data' => [
                        'current_password' =>
                            $currentPassword,

                        'new_password' =>
                            $newPassword,

                        'confirm_password' =>
                            $confirmPassword,
                    ],
                ],
                [
                    'data.current_password' => [
                        'required',
                        'string',
                    ],

                    'data.new_password' => [
                        'required',
                        'string',
                        Password::defaults(),
                    ],

                    'data.confirm_password' => [
                        'required',
                        'same:data.new_password',
                    ],
                ],
                [
                    'data.current_password.required' =>
                        'Password lama wajib diisi.',

                    'data.new_password.required' =>
                        'Password baru wajib diisi.',

                    'data.confirm_password.required' =>
                        'Konfirmasi password wajib diisi.',

                    'data.confirm_password.same' =>
                        'Konfirmasi password tidak sama '
                        . 'dengan password baru.',
                ]
            )->validate();

            // VALIDASI PASSWORD LAMA
            if (
                !Hash::check(
                    $currentPassword,
                    $user->password
                )
            ) {
                throw ValidationException::withMessages([
                    'data.current_password' =>
                        'Password lama tidak sesuai.',
                ]);
            }
        }

        // ======================================
        // VALIDASI SELURUH FORM
        // ======================================
        $formData = $this->form->getState();

        // ======================================
        // SIAPKAN DATA PENGATURAN
        // ======================================
        $settingData = collect($formData)
            ->only([
                'ph_min',
                'ph_max',

                'temperature_min',
                'temperature_max',

                'liquid_temperature_min',
                'liquid_temperature_max',

                'gas_min',
                'gas_max',

                'humidity_min',
                'humidity_max',

                'collection_interval',

                'whatsapp_number',
            ])
            ->toArray();

        // Catat interval sebelum diperbarui supaya MQTT hanya
        // dikirim saat nilainya benar-benar berubah.
        $oldInterval = $this->getSettingProperty()->collection_interval;
        $newInterval = (int) $formData['collection_interval'];
        $intervalChanged = (int) $oldInterval !== $newInterval;

        // ======================================
        // TRANSAKSI DATABASE
        // ======================================
        DB::transaction(function () use (
            $user,
            $formData,
            $settingData,
            $wantsPasswordChange,
            $newPassword
        ) {

            // ==============================
            // UPDATE PROFIL ADMIN
            // ==============================
            $user->name =
                $formData['full_name'];

            $user->email =
                $formData['email_address'];

            // ==============================
            // UPDATE PASSWORD (OPSIONAL)
            // ==============================
            if ($wantsPasswordChange) {
                $user->password =
                    Hash::make($newPassword);
            }

            $user->save();

            // ==============================
            // UPDATE SETTING
            // ==============================
            $setting = $this->getSettingProperty();

            $setting->forceFill(
                $settingData
            );

            $setting->save();
        });

        // ======================================
        // HAPUS PASSWORD DARI STATE FORM
        // ======================================
        $this->data['current_password'] = null;
        $this->data['new_password'] = null;
        $this->data['confirm_password'] = null;

        // ======================================
        // KIRIM INTERVAL VIA MQTT SETELAH COMMIT
        // ======================================
        // Jangan kirim di dalam transaksi database:
        // masalah jaringan tidak boleh membatalkan perubahan akun.
        if ($intervalChanged) {
            try {
                app(MqttPublisherService::class)
                    ->publishInterval($newInterval);
            } catch (\Throwable $e) {
                Log::warning('Gagal publish interval EnzyLife via MQTT', [
                    'interval_seconds' => $newInterval,
                    'error_type' => get_class($e),
                    // Hindari mencatat kredensial MQTT.
                ]);

                Notification::make()
                    ->title('Pengaturan tersimpan, MQTT belum berhasil')
                    ->body(
                        'Perubahan akun dan konfigurasi tersimpan di database, '
                        . 'tetapi interval belum berhasil dikirim ke ESP32. '
                        . 'Periksa koneksi HiveMQ. Pengiriman ulang '
                        . 'perlu dilakukan secara terpisah.'
                    )
                    ->warning()
                    ->send();

                return;
            }
        }

        // ======================================
        // NOTIFIKASI SUKSES
        // ======================================
        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->body(
                $wantsPasswordChange
                    ? 'Pengaturan dan password admin '
                        . 'berhasil diperbarui.'
                    : 'Pengaturan akun, threshold, '
                        . 'interval, dan WhatsApp '
                        . 'berhasil disimpan.'
            )
            ->success()
            ->send();
    }

    // ==========================================
    // 5. GET SETTINGS
    // ==========================================
    protected function getSettingProperty(): Setting
    {
        return Setting::firstOrCreate([]);
    }
}
