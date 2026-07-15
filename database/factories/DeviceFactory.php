<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'name'      => 'Device Test ' . $this->faker->unique()->word(),
            'location'  => 'Laboratorium Fermentasi',
            'user_id'   => User::factory(), // Otomatis membuat user relasi jika belum ada
            'sd_status' => 'CONNECTED',
        ];
    }
}