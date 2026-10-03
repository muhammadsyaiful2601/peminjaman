<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('role', 150)->nullable()->after('type');
            $table->string('student_id', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('students')->whereNull('student_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back employee roles while records without NIP exist. Add their NIP first.',
            );
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_id', 50)->nullable(false)->change();
            $table->dropColumn('role');
        });
    }
};
