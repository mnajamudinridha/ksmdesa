<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            // Same reason as pengangkutan.petugas_id: users are never deleted, and deleting a KSM
            // cascades its users — RESTRICT here would make that delete fail.
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            // The admin (or master administrator) who received the cash.
            $table->foreignId('diterima_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            // Calendar day in village time (App\Support\Hari).
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['ksm_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran');
    }
};
