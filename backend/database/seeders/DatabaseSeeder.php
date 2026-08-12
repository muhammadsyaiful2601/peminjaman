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
        User::firstOrCreate(
            ['email' => 'admin@kampus.ac.id'],
            ['name' => 'Admin Utama', 'password' => 'password', 'role' => 'admin']
        );

        // Assistant user
        User::firstOrCreate(
            ['email' => 'asisten@kampus.ac.id'],
            ['name' => 'Asisten Petugas', 'password' => 'password', 'role' => 'assistant']
        );

        // Sample items — barang-barang mahasiswa IT
        $items = [
            // Perangkat Keras
            ['item_code' => 'IT-HW-001', 'name' => 'Laptop ASUS ROG', 'category' => 'Perangkat Keras', 'stock' => 3],
            ['item_code' => 'IT-HW-002', 'name' => 'Laptop Acer Nitro', 'category' => 'Perangkat Keras', 'stock' => 4],
            ['item_code' => 'IT-HW-003', 'name' => 'Monitor LG 24 inch', 'category' => 'Perangkat Keras', 'stock' => 6],
            ['item_code' => 'IT-HW-004', 'name' => 'Keyboard Mechanical RGB', 'category' => 'Perangkat Keras', 'stock' => 10],
            ['item_code' => 'IT-HW-005', 'name' => 'Mouse Logitech G502', 'category' => 'Perangkat Keras', 'stock' => 12],
            ['item_code' => 'IT-HW-006', 'name' => 'Webcam Logitech C920', 'category' => 'Perangkat Keras', 'stock' => 8],
            ['item_code' => 'IT-HW-007', 'name' => 'Headset Gaming', 'category' => 'Perangkat Keras', 'stock' => 7],
            ['item_code' => 'IT-HW-008', 'name' => 'PC Rakitan (Core i7)', 'category' => 'Perangkat Keras', 'stock' => 5],

            // Jaringan & Server
            ['item_code' => 'IT-NW-001', 'name' => 'Router TP-Link', 'category' => 'Jaringan & Server', 'stock' => 10],
            ['item_code' => 'IT-NW-002', 'name' => 'Switch 8-Port', 'category' => 'Jaringan & Server', 'stock' => 8],
            ['item_code' => 'IT-NW-003', 'name' => 'Kabel UTP Cat6 (10m)', 'category' => 'Jaringan & Server', 'stock' => 30],
            ['item_code' => 'IT-NW-004', 'name' => 'Modem USB 4G', 'category' => 'Jaringan & Server', 'stock' => 5],
            ['item_code' => 'IT-NW-005', 'name' => 'Raspberry Pi 5', 'category' => 'Jaringan & Server', 'stock' => 6],
            ['item_code' => 'IT-NW-006', 'name' => 'UPS APC 600VA', 'category' => 'Jaringan & Server', 'stock' => 4],

            // Penyimpanan Data
            ['item_code' => 'IT-ST-001', 'name' => 'Flashdisk Kingston 64GB', 'category' => 'Penyimpanan Data', 'stock' => 20],
            ['item_code' => 'IT-ST-002', 'name' => 'SSD Samsung 500GB', 'category' => 'Penyimpanan Data', 'stock' => 8],
            ['item_code' => 'IT-ST-003', 'name' => 'HDD External WD 1TB', 'category' => 'Penyimpanan Data', 'stock' => 6],
            ['item_code' => 'IT-ST-004', 'name' => 'MicroSD 128GB + Adapter', 'category' => 'Penyimpanan Data', 'stock' => 15],

            // Presentasi & Perkuliahan
            ['item_code' => 'IT-PR-001', 'name' => 'Proyektor Epson EB-X51', 'category' => 'Presentasi & Perkuliahan', 'stock' => 4],
            ['item_code' => 'IT-PR-002', 'name' => 'Layar Proyektor Tripod', 'category' => 'Presentasi & Perkuliahan', 'stock' => 3],
            ['item_code' => 'IT-PR-003', 'name' => 'Presenter Logitech R800', 'category' => 'Presentasi & Perkuliahan', 'stock' => 5],
            ['item_code' => 'IT-PR-004', 'name' => 'Printer Canon PIXMA', 'category' => 'Presentasi & Perkuliahan', 'stock' => 3],

            // Robotika & IoT
            ['item_code' => 'IT-RB-001', 'name' => 'Arduino Uno Kit', 'category' => 'Robotika & IoT', 'stock' => 10],
            ['item_code' => 'IT-RB-002', 'name' => 'ESP32 DevKit', 'category' => 'Robotika & IoT', 'stock' => 15],
            ['item_code' => 'IT-RB-003', 'name' => 'Sensor Kit (20 sensor)', 'category' => 'Robotika & IoT', 'stock' => 8],
            ['item_code' => 'IT-RB-004', 'name' => 'Drone DJI Tello', 'category' => 'Robotika & IoT', 'stock' => 3],
            ['item_code' => 'IT-RB-005', 'name' => 'Rover Chassis Kit', 'category' => 'Robotika & IoT', 'stock' => 5],
            ['item_code' => 'IT-RB-006', 'name' => 'Oscilloscope portable', 'category' => 'Robotika & IoT', 'stock' => 4],

            // Aksesoris & Perlengkapan
            ['item_code' => 'IT-AC-001', 'name' => 'Laptop Stand Adjustable', 'category' => 'Aksesoris & Perlengkapan', 'stock' => 10],
            ['item_code' => 'IT-AC-002', 'name' => 'HUB USB-C 7 in 1', 'category' => 'Aksesoris & Perlengkapan', 'stock' => 8],
            ['item_code' => 'IT-AC-003', 'name' => 'Cooling Pad Laptop', 'category' => 'Aksesoris & Perlengkapan', 'stock' => 7],
            ['item_code' => 'IT-AC-004', 'name' => 'Tas Laptop 15.6 inch', 'category' => 'Aksesoris & Perlengkapan', 'stock' => 12],
            ['item_code' => 'IT-AC-005', 'name' => 'Power Bank 20000mAh', 'category' => 'Aksesoris & Perlengkapan', 'stock' => 9],
        ];

        foreach ($items as $item) {
            Item::firstOrCreate(
                ['item_code' => $item['item_code']],
                $item
            );
        }
    }
}