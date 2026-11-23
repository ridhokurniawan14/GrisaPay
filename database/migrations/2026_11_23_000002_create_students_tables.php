<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Salinan lokal siswa, penempatan kelas per tahun ajaran, dan wali (WA). Diisi HANYA oleh sinkronisasi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('grisaid_id')->unique();        // ID siswa di GrisaID (acuan antar-aplikasi)
            $table->string('nisn', 10)->nullable()->index();
            $table->string('nis', 30)->nullable()->index();
            $table->string('name');
            $table->string('gender', 1);
            $table->string('phone', 20)->nullable();
            $table->unsignedSmallInteger('entry_year')->index();
            $table->string('status', 10)->index();       // aktif | lulus | mutasi | do
            $table->timestamp('grisaid_updated_at')->nullable();
            $table->timestamp('grisaid_deleted_at')->nullable(); // dihapus di GrisaID (tetap disimpan: riwayat tagihan)
            $table->timestamps();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('status', 15)->default('aktif');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 10);              // ayah | ibu | wali
            $table->string('name')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
    }
};
