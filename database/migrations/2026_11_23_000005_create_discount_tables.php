<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis diskon (Prestasi, Tidak mampu, Yatim piatu, ...). Nilai bawaan hanya mengisi form, tidak mengikat.
        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('default_scope', 10)->default('bill');   // bill | category | item
            $table->foreignId('default_fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete();
            $table->string('default_mode', 10)->default('percent'); // percent | amount
            $table->unsignedBigInteger('default_value')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pengajuan diskon. Setelah disetujui, potongan nyata ada di discount_allocations.
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('discount_type_id')->constrained()->restrictOnDelete();
            $table->string('scope', 10);                              // bill | category | item
            $table->foreignId('fee_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bill_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mode', 10);                               // percent | amount
            $table->unsignedBigInteger('value');                      // 1..100 bila percent, rupiah bila amount
            $table->text('reason');
            $table->string('status', 12)->default('diajukan')->index(); // diajukan | disetujui | ditolak | dicabut
            $table->unsignedBigInteger('granted_amount')->default(0); // potongan rupiah saat disetujui
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['discount_id', 'bill_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_allocations');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('discount_types');
    }
};
