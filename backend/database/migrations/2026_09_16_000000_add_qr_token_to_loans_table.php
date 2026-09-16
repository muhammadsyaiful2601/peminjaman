<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->uuid('qr_token')->nullable()->unique()->after('uuid');
        });

        DB::table('loans')->whereNull('qr_token')->orderBy('id')->each(function ($loan): void {
            DB::table('loans')->where('id', $loan->id)->update(['qr_token' => $loan->uuid]);
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
