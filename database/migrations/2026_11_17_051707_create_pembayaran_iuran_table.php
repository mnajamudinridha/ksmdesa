<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_iuran', function (Blueprint $table) {
            $table->id();
            // Denormalised from pelanggan so tenant scoping and period sums need no join.
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            // Always the first day of the month being paid for.
            $table->date('periode');
            $table->unsignedInteger('nominal');
            $table->date('dibayar_pada')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pelanggan_id', 'periode']);
            $table->index(['ksm_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_iuran');
    }
};
