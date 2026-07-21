<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use App\Models\Setting;

class Pengaturan extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.pengaturan';

    public ?array $data = [];


    // ─────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────
    public function mount(): void
    {
        $user = Auth::user();
        $setting = $this->getSettingProperty();

        $this->form->fill([
            'full_name' => $user?->name,
            'email_address' => $user?->email,

            'ph_min' => $setting->ph_min,
            'ph_max' => $setting->ph_max,
            'temperature_min' => $setting->temperature_min,
            'temperature_max' => $setting->temperature_max,
            'liquid_temperature_min' => $setting->liquid_temperature_min,
            'liquid_temperature_max' => $setting->liquid_temperature_max,
            'gas_min' => $setting->gas_min,
            'gas_max' => $setting->gas_max,
            'humidity_min' => $setting->humidity_min,
            'humidity_max' => $setting->humidity_max,

            'collection_interval' => $setting->collection_interval,
        ]);
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    // ─────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([

                Tabs::make('Pengaturan Tabs')
                    ->tabs([

                        // ======================
                        // TAB: BATAS AMBANG SENSOR
                        // ======================
                        Tab::make('Ambang Batas Sensor')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([

                                Section::make('Batas Ambang Nilai Sensor')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        $this->rangeField('ph', 'pH'),
                                        $this->rangeField('temperature', 'Suhu Udara'),
                                        $this->rangeField('liquid_temperature', 'Suhu Cairan'),
                                        $this->rangeField('gas', 'Gas (ppm)'),
                                        $this->rangeField('humidity', 'Kelembapan (%)'),
                                    ]),

                                Section::make('Interval Pengambilan Data')
                                    ->schema([
                                        TextInput::make('collection_interval')
                                            ->label('Interval Waktu (Detik)')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(3600)
                                            ->required(),
                                    ]),
                            ]),

                        // ======================
                        // TAB: AKUN
                        // ======================
                        Tab::make('Akun Pengguna')
                            ->icon('heroicon-o-user')
                            ->schema([

                                Section::make('Informasi Akun')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        TextInput::make('full_name')
                                            ->label('Nama Lengkap')
                                            ->required(),

                                        TextInput::make('email_address')
                                            ->label('Alamat Email')
                                            ->email()
                                            ->required(),
                                    ])
                                    ->columns(2),

                                Section::make('Ubah Kata Sandi')
                                    ->schema([
                                        TextInput::make('current_password')
                                            ->label('Kata Sandi Saat Ini')
                                            ->password()
                                            ->dehydrated(false),

                                        TextInput::make('new_password')
                                            ->label('Kata Sandi Baru')
                                            ->password()
                                            ->rule(Password::defaults())
                                            ->dehydrated(false),

                                        TextInput::make('confirm_password')
                                            ->label('Konfirmasi Kata Sandi Baru')
                                            ->password()
                                            ->same('new_password')
                                            ->dehydrated(false),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),

            ]);
    }

    // ─────────────────────────────────────────
    // DRY HELPER
    // ─────────────────────────────────────────
    protected function rangeField(string $key, string $label): Grid
    {
        return Grid::make(2)->schema([
            TextInput::make("{$key}_min")
                ->label("{$label} Minimal")
                ->numeric()
                ->inputMode('decimal')
                ->required(),

            TextInput::make("{$key}_max")
                ->label("{$label} Maksimal")
                ->numeric()
                ->inputMode('decimal')
                ->gte("{$key}_min")
                ->required(),
        ]);
    }

    // ─────────────────────────────────────────
    // SAVE
    // ─────────────────────────────────────────
    public function save(): void
    {
        $formData = $this->form->getState();

        $user = Auth::user();
        if ($user) {
            $user->update([
                'name' => $formData['full_name'],
                'email' => $formData['email_address'],
            ]);
        }

        $settingData = collect($formData)->only([
            'ph_min', 'ph_max',
            'temperature_min', 'temperature_max',
            'liquid_temperature_min', 'liquid_temperature_max',
            'gas_min', 'gas_max',
            'humidity_min', 'humidity_max',
            'collection_interval'
        ])->toArray();

        $this->getSettingProperty()->update($settingData);

        Notification::make()
            ->title('Pengaturan Berhasil Disimpan')
            ->success()
            ->send();
    }

    // ─────────────────────────────────────────
    // GETTERS
    // ─────────────────────────────────────────
    protected function getSettingProperty(): Setting
    {
        return Setting::firstOrCreate([]);
    }
}