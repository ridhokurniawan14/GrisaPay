<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Isi struk dibekukan saat pembayaran sah, supaya cetak ulang selalu identik dengan aslinya.
        Schema::table('payments', function (Blueprint $table) {
            $table->json('receipt_snapshot')->nullable()->after('reject_reason');
            // Satu kunci per pengiriman form: klik ganda / kirim ulang tidak pernah membuat pembayaran kedua.
            $table->string('idempotency_key', 64)->nullable()->unique()->after('receipt_no');
        });

        // Setiap kali struk dicetak: copy_no 0 = cetak pertama (asli), 1 = cetak ulang ke-1, dst.
        Schema::create('payment_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('copy_no');
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at');
            $table->timestamps();

            $table->unique(['payment_id', 'copy_no']);
        });

        // Satu baris per berkas impor tunggakan/saldo awal, agar bisa dibatalkan satu paket.
        Schema::create('arrear_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->json('created_bill_ids')->nullable();
            $table->string('status', 12)->default('aktif');   // aktif | dibatalkan
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('arrear_import_id')->nullable()->after('fee_component_id')
                ->constrained('arrear_imports')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arrear_import_id');
        });
        Schema::dropIfExists('arrear_imports');
        Schema::dropIfExists('payment_prints');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_snapshot', 'idempotency_key']);
        });
    }
};
