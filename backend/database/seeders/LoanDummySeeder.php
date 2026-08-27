<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoanDummySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $items = Item::all();

        if ($items->isEmpty()) {
            $itemData = [
                ['code' => 'BRG-001', 'name' => 'Arduino Uno Kit', 'category' => 'Elektronik'],
                ['code' => 'BRG-002', 'name' => 'Cooling Pad Laptop', 'category' => 'Aksesoris'],
                ['code' => 'BRG-003', 'name' => 'Laptop ASUS ROG', 'category' => 'Komputer'],
                ['code' => 'BRG-004', 'name' => 'Keyboard Mechanical RGB', 'category' => 'Aksesoris'],
                ['code' => 'BRG-005', 'name' => 'Harddisk Kingston', 'category' => 'Penyimpanan'],
            ];

            foreach ($itemData as $item) {
                Item::create([
                    'item_code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'stock' => 10,
                ]);
            }

            $items = Item::all();
        }

        Loan::where('loan_code', 'like', 'DUMMY-PJM-%')->delete();

        $statuses = ['borrowed', 'returned', 'returned', 'borrowed', 'pending'];

        for ($index = 1; $index <= 100; $index++) {
            $createdAt = fake()->dateTimeBetween('-12 months', 'now');
            $status = fake()->randomElement($statuses);
            $borrowedAt = in_array($status, ['borrowed', 'returned'], true) ? $createdAt : null;
            $returnedAt = $status === 'returned'
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null;
            $item = $items->random();
            $quantity = fake()->numberBetween(1, 3);

            $loan = Loan::create([
                'uuid' => (string) Str::uuid(),
                'loan_code' => sprintf('DUMMY-PJM-%04d', $index),
                'item_id' => $item->id,
                'qty' => $quantity,
                'borrower_name' => fake()->name(),
                'borrower_email' => fake()->safeEmail(),
                'borrower_phone' => fake()->numerify('08##########'),
                'borrower_student_id' => fake()->numerify('##########'),
                'status' => $status,
                'borrowed_at' => $borrowedAt,
                'returned_at' => $returnedAt,
                'created_by' => $admin->id,
                'verified_by' => $status === 'pending' ? null : $admin->id,
            ]);

            $loan->created_at = $createdAt;
            $loan->updated_at = $createdAt;
            $loan->save();

            LoanItem::create([
                'loan_id' => $loan->id,
                'item_id' => $item->id,
                'qty' => $quantity,
            ]);
        }
    }
}