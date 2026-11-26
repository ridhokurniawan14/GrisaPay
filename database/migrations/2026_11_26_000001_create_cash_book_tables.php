<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Pengeluaran, pemasukan lain, mutasi kas↔bank, dan saldo awal untuk Buku Kas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('kind', 6);                 // keluar | masuk | mutasi
            $table->string('account', 4);              // kas | bank
            $table->string('direction', 3);            // in | out (arah terhadap akun)
            $table->unsignedBigInteger('amount');
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->string('description');
            $table->string('party')->nullable();       // penerima / pemberi
            $table->string('reference_no', 60)->nullable(); // no. nota / bukti
            $table->string('proof_path')->nullable();
            $table->uuid('transfer_group')->nullable()->index();
            $table->string('status', 6)->default('sah'); // sah | batal
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['account', 'entry_date']);
            $table->index(['kind', 'entry_date']);
        });

        Schema::create('cash_book_openings', function (Blueprint $table) {
            $table->id();
            $table->string('account', 4)->unique();    // kas | bank
            $table->bigInteger('amount')->default(0);  // saldo pada AWAL tanggal as_of
            $table->date('as_of');
            $table->string('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('expense_categories')->insert(array_map(fn (string $n): array => ['name' => $n, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            'Gaji & honor', 'ATK & perlengkapan', 'Listrik, air & internet', 'Pemeliharaan & perbaikan',
            'Kegiatan siswa', 'Operasional kantor', 'Transport & konsumsi', 'Lain-lain',
        ]));

        // Daftarkan izin baru & berikan ke peran bawaan (bisa diubah lagi dari halaman Peran).
        if (class_exists(Permission::class) && Schema::hasTable('permissions')) {
            $view = Permission::findOrCreate('expenses.view', 'web');
            $manage = Permission::findOrCreate('expenses.manage', 'web');

            Role::query()->where('name', 'bendahara')->first()?->givePermissionTo([$view, $manage]);
            Role::query()->where('name', 'kepala_sekolah')->first()?->givePermissionTo([$view]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_book_openings');
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('expense_categories');
    }
};
