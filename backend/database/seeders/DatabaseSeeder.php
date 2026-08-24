<?php

namespace Database\Seeders;

use App\Models\User;
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
            ['email' => 'admin@kampus.ac.id'],
            ['name' => 'Admin Utama', 'username' => 'admin', 'password' => 'password', 'role' => 'admin']
        );

        // Assistant user
        User::firstOrCreate(
            ['email' => 'asisten@kampus.ac.id'],
            ['name' => 'Asisten Petugas', 'username' => 'asisten', 'password' => 'password', 'role' => 'assistant', 'email_verified_at' => now()]
        );
    }
}