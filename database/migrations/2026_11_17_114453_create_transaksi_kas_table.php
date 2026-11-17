<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->date('tanggal');
            // masuk | keluar — kept on the transaction itself: the sign must survive its category.
            $table->string('jenis', 10);
            // Null-on-delete, not restrict: deleting a KSM cascades through every one of its tables,
            // and a restrict between them would block it. The policies keep used master rows.
            $table->foreignId('kategori_transaksi_id')->nullable()->constrained('kategori_transaksi')->nullOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained('mitra')->nullOnDelete();
            // Rupiah, whole numbers.
            $table->unsignedBigInteger('nominal');
            $table->string('uraian');
            // Receipt photo/PDF on the private "local" disk.
            $table->string('bukti')->nullable();
            $table->foreignId('dicatat_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ksm_id', 'tanggal']);
            $table->index(['ksm_id', 'jenis', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_kas');
    }
};
