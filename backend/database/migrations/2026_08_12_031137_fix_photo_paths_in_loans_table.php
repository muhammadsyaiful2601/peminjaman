<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('loans')->whereNotNull('borrow_photo')->get()->each(function ($loan) {
            $photo = $loan->borrow_photo;
            // If path starts with 'storage/', remove it
            if (str_starts_with($photo, 'storage/')) {
                DB::table('loans')->where('id', $loan->id)->update(['borrow_photo' => substr($photo, 8)]);
            }
        });

        DB::table('loans')->whereNotNull('return_photo')->get()->each(function ($loan) {
            $photo = $loan->return_photo;
            // If path starts with 'storage/', remove it
            if ($photo && str_starts_with($photo, 'storage/')) {
                DB::table('loans')->where('id', $loan->id)->update(['return_photo' => substr($photo, 8)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            //
        });
    }
};
