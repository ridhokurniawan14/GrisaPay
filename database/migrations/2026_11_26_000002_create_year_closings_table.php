<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per tahun ajaran. closed_at NULL = sedang dibuka kembali.
        Schema::create('year_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();   // ringkasan saat ditutup (tagihan, terbayar, sisa, jumlah siswa)
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_closings');
    }
};
