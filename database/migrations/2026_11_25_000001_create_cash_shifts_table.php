<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opening_cash')->default(0);

            // Diisi saat shift ditutup. expected_cash = modal awal + tunai sah selama shift (snapshot).
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();   // counted - expected (negatif = kurang)
            $table->text('close_note')->nullable();

            $table->string('status', 10)->default('open')->index(); // open | closed | reviewed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        // Satu kasir hanya boleh punya satu shift terbuka (dijaga di database, bukan hanya di aplikasi).
        DB::statement("CREATE UNIQUE INDEX cash_shifts_one_open_per_user ON cash_shifts (user_id) WHERE status = 'open'");

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_shift_id');
        });

        Schema::dropIfExists('cash_shifts');
    }
};
