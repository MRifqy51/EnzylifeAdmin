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
                    // TAB: THRESHOLDS
                    // ======================
                    Tab::make('Thresholds')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([

                            Section::make('Thresholds')
                                ->icon('heroicon-o-adjustments-horizontal')
                                ->schema([
                                    $this->rangeField('ph', 'pH'),
                                        $this->rangeField('temperature', 'Air Temp'),
                                        $this->rangeField('liquid_temperature', 'Liquid Temp'),
                                        $this->rangeField('gas', 'Gas (ppm)'),
                                        $this->rangeField('humidity', 'Humidity (%)'),
                                ]),

                            Section::make('Data Interval')
                                ->schema([
                                    TextInput::make('collection_interval')
                                        ->label('Interval (seconds)')
                                        ->numeric()
                                        ->minValue(5)
                                        ->maxValue(3600)
                                        ->required(),
                                ]),
                        ]),

                    // ======================
                    // TAB: ACCOUNT
                    // ======================
                    Tab::make('Account')
                        ->icon('heroicon-o-user')
                        ->schema([

                            Section::make('Account')
                                ->icon('heroicon-o-user')
                                ->schema([
                                    TextInput::make('full_name')
                                        ->label('Full Name')
                                        ->required(),

                                    TextInput::make('email_address')
                                        ->label('Email')
                                        ->email()
                                        ->required(),
                                ])
                                ->columns(2),

                            Section::make('Change Password')
                                ->schema([
                                    TextInput::make('current_password')
                                        ->label('Current Password')
                                        ->password()
                                        ->dehydrated(false),

                                    TextInput::make('new_password')
                                        ->label('New Password')
                                        ->password()
                                        ->rule(Password::defaults())
                                        ->dehydrated(false),

                                    TextInput::make('confirm_password')
                                        ->label('Confirm Password')
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

    \Filament\Notifications\Notification::make()
        ->title('Berhasil disimpan')
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