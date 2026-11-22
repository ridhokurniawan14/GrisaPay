<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('singleton')->default(true)->unique(); // memastikan hanya ada SATU baris pengaturan

            // Identitas
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('npsn', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();           // kota untuk tanggal pada tanda tangan dokumen
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Tampilan
            $table->string('logo_path')->nullable();      // disk public
            $table->string('primary_color', 7)->default('#6366f1');

            // Penandatangan dokumen (file tanda tangan di disk private)
            $table->string('principal_name')->nullable();
            $table->string('principal_nip', 30)->nullable();
            $table->string('principal_signature_path')->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('treasurer_nip', 30)->nullable();
            $table->string('treasurer_signature_path')->nullable();

            // Pembayaran
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('qris_image_path')->nullable(); // disk private
            $table->string('receipt_footer', 120)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_settings');
    }
};
