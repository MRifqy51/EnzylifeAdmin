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

    public function getSubheading(): ?string
    {
        return null;
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
                    ->id('setting-tab')
                    ->persistTabInQueryString()
                    ->tabs([

                        // ==========================
                        // TAB: THRESHOLDS (READ-ONLY)
                        // ==========================
                        Tab::make('Ambang Batas Sensor')
                            ->icon(
                                'heroicon-o-adjustments-horizontal'
                            )
                            ->schema([

                                Section::make('Batas Ambang Nilai Sensor')
                                    ->description('Nilai batas ambang bersifat tetap sesuai standar parameter sistem fermentasi Eco Enzyme.')
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

                                        $this->rangeField(
                                            'gas',
                                            'Gas (Raw ADC)'
                                        ),

                                        $this->rangeField(
                                            'humidity',
                                            'Humidity (%)'
                                        ),
                                    ]),
                            ]),

                        // ==========================
                        // TAB: ACCOUNT (EDITABLE)
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
                                            ->placeholder('Masukkan kata sandi saat ini')
                                            ->revealable()
                                            ->dehydrated(false),

                                        TextInput::make(
                                            'new_password'
                                        )
                                            ->label(
                                                'New Password'
                                            )
                                            ->password()
                                            ->placeholder('Masukkan kata sandi baru')
                                            ->revealable()
                                            ->dehydrated(false),

                                        TextInput::make(
                                            'confirm_password'
                                        )
                                            ->label(
                                                'Konfirmasi Kata Sandi Baru'
                                            )
                                            ->password()
                                            ->placeholder('Ulangi kata sandi baru')
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
    // 3. THRESHOLD HELPER (LOCKED / READ-ONLY)
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
                    ->disabled() // Dikunci agar tidak bisa diedit/dipencet
                    ->dehydrated(false)
                    ->helperText('Nilai threshold tetap dan tidak dapat diubah.'),

                TextInput::make("{$key}_max")
                    ->label("{$label} Max")
                    ->numeric()
                    ->disabled() // Dikunci agar tidak bisa diedit/dipencet
                    ->dehydrated(false)
                    ->helperText('Nilai threshold tetap dan tidak dapat diubah.'),
            ]);
    }

    // ==========================================
    // 4. SAVE ALL SETTINGS (ACCOUNT ONLY)
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

        $currentPassword = (string) ($this->data['current_password'] ?? '');
        $newPassword = (string) ($this->data['new_password'] ?? '');
        $confirmPassword = (string) ($this->data['confirm_password'] ?? '');

        $wantsPasswordChange =
            $currentPassword !== '' ||
            $newPassword !== '' ||
            $confirmPassword !== '';

        if ($wantsPasswordChange) {
            Validator::make(
                [
                    'data' => [
                        'current_password' => $currentPassword,
                        'new_password' => $newPassword,
                        'confirm_password' => $confirmPassword,
                    ],
                ],
                [
                    'data.current_password' => ['required', 'string'],
                    'data.new_password' => ['required', 'string', Password::defaults()],
                    'data.confirm_password' => ['required', 'same:data.new_password'],
                ],
                [
                    'data.current_password.required' => 'Password lama wajib diisi.',
                    'data.new_password.required' => 'Password baru wajib diisi.',
                    'data.confirm_password.required' => 'Konfirmasi password wajib diisi.',
                    'data.confirm_password.same' => 'Konfirmasi password tidak sama dengan password baru.',
                ]
            )->validate();

            if (!Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'data.current_password' => 'Password lama tidak sesuai.',
                ]);
            }
        }

        $formData = $this->form->getState();

        // Hanya simpan WhatsApp number, threshold & interval diabaikan karena read-only/dihapus
        $settingData = [
            'whatsapp_number' => $formData['whatsapp_number'] ?? null,
        ];

        DB::transaction(function () use (
            $user,
            $formData,
            $settingData,
            $wantsPasswordChange,
            $newPassword
        ) {
            $user->name = $formData['full_name'];
            $user->email = $formData['email_address'];

            if ($wantsPasswordChange) {
                $user->password = Hash::make($newPassword);
            }

            $user->save();

            $setting = $this->getSettingProperty();
            $setting->forceFill($settingData);
            $setting->save();
        });

        $this->data['current_password'] = null;
        $this->data['new_password'] = null;
        $this->data['confirm_password'] = null;

        Notification::make()
            ->title('Pengaturan Akun Berhasil Disimpan')
            ->body(
                $wantsPasswordChange
                    ? 'Informasi profil dan password admin berhasil diperbarui.'
                    : 'Informasi profil dan nomor WhatsApp berhasil diperbarui.'
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