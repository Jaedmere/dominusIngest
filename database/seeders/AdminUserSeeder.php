<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jaedmere1993@gmail.com'],
            [
                'name' => 'Jaime Mejía',
                'password' => Hash::make('Jaime1993+ja'),
                'email_verified_at' => now(),
            ]
        );
    }
}
