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

        $this->form->fill([
            'full_name' => $user?->name,
            'email_address' => $user?->email,

            'ph_min' => 4,
            'ph_max' => 6.5,
            'liquid_temp_min' => 20,
            'liquid_temp_max' => 35,
            'gas_min' => 0,
            'gas_max' => 500,
            'air_temp_min' => 15,
            'air_temp_max' => 32,
            'humidity_min' => 40,
            'humidity_max' => 85,

            'collection_interval' => 30,
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
                                    $this->rangeField('liquid_temp', 'Liquid Temp'),
                                    $this->rangeField('gas', 'Gas'),
                                    $this->rangeField('air_temp', 'Air Temp'),
                                    $this->rangeField('humidity', 'Humidity'),
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
            ->required(),

        TextInput::make("{$key}_max")
            ->label("{$label} Max")
            ->numeric()
            ->gte("{$key}_min")
            ->required(),
    ]);
}
    // ─────────────────────────────────────────
    // SAVE
    // ─────────────────────────────────────────
    public function save(): void
{
    $data = $this->form->getState();

    /** @var \App\Models\User $user */
    $user = Auth::user();

        // PROFILE
        $user->update([
            'name' => $data['full_name'],
            'email' => $data['email_address'],
        ]);

        // PASSWORD (SAFE)
        if (!empty($data['current_password']) || !empty($data['new_password'])) {

            if (!Hash::check($data['current_password'], $user->password)) {
                $this->addError('data.current_password', 'Password lama salah');
                return;
            }

            if (empty($data['new_password'])) {
                $this->addError('data.new_password', 'Password baru wajib diisi');
                return;
            }

            $user->update([
                'password' => Hash::make($data['new_password']),
            ]);
        }

        Notification::make()
            ->title('Berhasil disimpan')
            ->success()
            ->send();
    }
}