<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengangkutan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            // Users are deactivated, never deleted; this cascade only runs when a whole KSM is
            // deleted (which cascades its users too — RESTRICT here would make that delete fail).
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            // Calendar day in village time (App\Support\Hari), not a UTC instant.
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->timestamps();

            // One round per petugas per day; the form edits it instead of adding another.
            $table->unique(['petugas_id', 'tanggal']);
            $table->index(['ksm_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengangkutan');
    }
};
