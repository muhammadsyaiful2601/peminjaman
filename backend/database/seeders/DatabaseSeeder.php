<?php

namespace Database\Seeders;

use App\Models\Item;
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
        User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@kampus.ac.id',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // Assistant user
        User::create([
            'name' => 'Asisten Petugas',
            'email' => 'asisten@kampus.ac.id',
            'password' => 'password',
            'role' => 'assistant',
        ]);

        // Sample items
        $items = [
            ['item_code' => 'ELE-001', 'name' => 'Proyektor Epson', 'category' => 'Elektronik', 'stock' => 10],
            ['item_code' => 'ELE-002', 'name' => 'Laptop Dell', 'category' => 'Elektronik', 'stock' => 5],
            ['item_code' => 'ELE-003', 'name' => 'Speaker Aktif', 'category' => 'Elektronik', 'stock' => 8],
            ['item_code' => 'OLR-001', 'name' => 'Bola Voli', 'category' => 'Olahraga', 'stock' => 15],
            ['item_code' => 'OLR-002', 'name' => 'Raket Badminton', 'category' => 'Olahraga', 'stock' => 12],
            ['item_code' => 'OLR-003', 'name' => 'Matras Yoga', 'category' => 'Olahraga', 'stock' => 20],
            ['item_code' => 'LAB-001', 'name' => 'Mikroskop', 'category' => 'Laboratorium', 'stock' => 6],
            ['item_code' => 'LAB-002', 'name' => 'Tabung Reaksi', 'category' => 'Laboratorium', 'stock' => 50],
            ['item_code' => 'KSR-001', 'name' => 'Kursi Lipat', 'category' => 'Perlengkapan', 'stock' => 30],
            ['item_code' => 'KSR-002', 'name' => 'Meja Lipat', 'category' => 'Perlengkapan', 'stock' => 25],
        ];

        foreach ($items as $item) {
            Item::create($item);
        }
    }
}