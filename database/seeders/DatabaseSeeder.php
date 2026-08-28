<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@assesg.org.br'],
            [
                'name' => 'Administrador ASSESG',
                'password' => Hash::make('assesg@2026'),
                'is_main_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
