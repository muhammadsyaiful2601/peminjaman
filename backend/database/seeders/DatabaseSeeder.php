<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Technician;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Admin Utama', 'username' => 'admin', 'password' => 'password', 'role' => 'admin']
        );

        // Assistant user
        User::firstOrCreate(
            ['username' => 'asisten'],
            ['name' => 'Asisten Petugas', 'username' => 'asisten', 'password' => 'password', 'role' => 'assistant', 'email_verified_at' => now()]
        );

        Technician::firstOrCreate(
            ['nip' => '197907182025211025'],
            ['name' => 'NOFA HENDRAYANA.ST']
        );
    }
}