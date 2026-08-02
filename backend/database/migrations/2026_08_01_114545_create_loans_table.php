<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('loan_code')->unique();
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->integer('qty')->default(1);
            // Borrower info stored directly (no login required for mahasiswa)
            $table->string('borrower_name');
            $table->string('borrower_email');
            $table->string('borrower_phone')->nullable();
            $table->string('borrower_student_id')->nullable();
            $table->string('borrow_photo')->nullable();
            $table->enum('status', ['pending', 'borrowed', 'returned', 'rejected'])->default('pending');
            $table->timestamp('borrowed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            // Petugas who created the loan
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('condition_on_return')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};