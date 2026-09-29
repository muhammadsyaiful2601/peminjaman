<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Kategori peminjam: mahasiswa (default), tendik, dosen, atau umum.
            // Data lama otomatis jadi "mahasiswa" karena memakai nilai default.
            $table->string('type', 20)->default('mahasiswa')->after('student_id')->index();
            // Jabatan/prodi/publisher, mis. "Dosen Teknik Informatika" atau
            // "Staf Bagian Keuangan". Nullable karena tidak semua peminjam
            // punya jabatan (peminjam umum boleh kosong).
            $table->string('position', 150)->nullable()->after('name');
        });

        Schema::table('loans', function (Blueprint $table) {
            // Jenis peminjam saat transaksi dibuat. Nullable karena transaksi
            // lama tidak punya kolom ini; null dibaca sebagai "mahasiswa" oleh
            // Loan::borrowerType() supaya tidak perlu menulis ulang tabel.
            $table->string('borrower_type', 20)->nullable()->after('borrower_student_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('borrower_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['type', 'position']);
        });
    }
};
