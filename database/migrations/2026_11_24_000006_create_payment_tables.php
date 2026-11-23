<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran. Transaksi TIDAK boleh diedit/dihapus: koreksi hanya lewat void yang disetujui bendahara.
 * Nomor kwitansi berurutan dan tidak pernah dipakai ulang (void tetap memegang nomornya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->nullable()->unique();      // diisi saat pembayaran sah
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete(); // tahun ajaran aktif saat dicatat
            $table->timestamp('paid_at');
            $table->string('method', 10);                                // cash | transfer | qris
            $table->unsignedBigInteger('amount');
            $table->string('focus', 20)->nullable();                     // null = tertua dulu | carry | cat:ID
            $table->string('reference', 100)->nullable();                // no. referensi transfer / QRIS
            $table->string('payer_name', 100)->nullable();               // penyetor (ortu/siswa)
            $table->text('note')->nullable();
            $table->string('status', 12)->default('menunggu')->index();  // menunggu | sah | ditolak | void
            $table->text('reject_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('paid_at');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['payment_id', 'bill_item_id']);
            $table->index('bill_item_id');
        });

        Schema::create('payment_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 12)->default('diajukan')->index();  // diajukan | disetujui | ditolak
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_voids');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipt_sequences');
    }
};
