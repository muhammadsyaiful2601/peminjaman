<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->whereIn('type', ['tendik', 'dosen'])
            ->whereRaw('LOWER(COALESCE(position, \'\')) LIKE ?', ['%dosen%'])
            ->update(['type' => 'dosen', 'updated_at' => now()]);

        DB::table('students')
            ->whereIn('type', ['tendik', 'dosen'])
            ->whereRaw('LOWER(COALESCE(position, \'\')) NOT LIKE ?', ['%dosen%'])
            ->update(['type' => 'tendik', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The previous category cannot be reconstructed from the position field.
    }
};
