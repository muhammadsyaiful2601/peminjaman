<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            // Jabatan/peran laboratorium, mis. "Teknisi Lab Komputer/RPL".
            $table->string('position')->nullable()->after('name');
            // Nomor WhatsApp untuk kontak teknis.
            $table->string('whatsapp', 30)->nullable()->after('nip');
            // Tanda tangan digital (PNG/JPG/SVG) tersimpan di disk "public".
            $table->string('signature_path')->nullable()->after('whatsapp');
            // Hanya satu teknisi yang menjadi penandatangan utama.
            $table->boolean('is_primary')->default(false)->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->dropColumn(['position', 'whatsapp', 'signature_path', 'is_primary']);
        });
    }
};
