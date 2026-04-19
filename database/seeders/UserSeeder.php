<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@gmail.com'], // cek dulu
            [
                'name' => 'Admin',
                'password' => bcrypt('admin123'),
            ]
        );
    }
}