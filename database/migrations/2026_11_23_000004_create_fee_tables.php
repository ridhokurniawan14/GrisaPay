<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori biaya (tampil di struk), komponen tarif per tahun ajaran x tingkat (sama untuk semua jurusan),
 * serta tagihan siswa. Semua uang = bilangan bulat rupiah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();      // personal, insidental, pengembangan, psm, tunggakan
            $table->string('name');                    // nama lengkap untuk admin
            $table->string('receipt_label', 40);       // label singkat di struk
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false); // true = tidak boleh dihapus (Tunggakan sebelumnya)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rincian tarif. Kategori bisa dipakai sebagian tingkat saja (mis. Pengembangan hanya kelas 10).
        Schema::create('fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('grade');      // 10 | 11 | 12
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->string('name');                    // "Seragam batik", "Kegiatan OSIS", ...
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['academic_year_id', 'grade']);
        });

        // Satu tagihan per siswa per tahun ajaran. Kelas disalin (snapshot) agar laporan lama tidak berubah.
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->string('classroom_name', 50);
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });

        // Isi tagihan = salinan komponen saat dibuat. Perubahan tarif tidak mengubah tagihan yang sudah ada.
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('discount_amount')->default(0); // diisi lewat alur persetujuan diskon (tahap berikut)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bill_id', 'fee_component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('fee_components');
        Schema::dropIfExists('fee_categories');
    }
};
