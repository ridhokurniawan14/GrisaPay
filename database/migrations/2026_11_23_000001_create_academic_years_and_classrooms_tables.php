<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Salinan lokal data master GrisaID. Diisi HANYA oleh sinkronisasi; jangan diedit manual. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->uuid('grisaid_id')->unique();
            $table->string('code', 9)->unique(); // 2026/2027
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('grisaid_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('grade')->index();      // 10 | 11 | 12
            $table->string('name', 50);                         // "X KULINER 1"
            $table->unsignedTinyInteger('group_no')->nullable();
            $table->string('major_code', 10)->nullable();
            $table->string('major_name')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('academic_years');
    }
};
