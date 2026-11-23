<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 10)->index();       // running | success | failed
            $table->string('trigger', 10);               // manual | scheduled
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
