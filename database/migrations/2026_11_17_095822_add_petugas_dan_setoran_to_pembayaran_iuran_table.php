<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran_iuran', function (Blueprint $table) {
            // Who took the cash: a petugas in the field. Null = received directly by the
            // committee (admin form, CSV import) — never needs depositing.
            $table->foreignId('petugas_id')->nullable()->after('pelanggan_id')->constrained('users')->nullOnDelete();
            // The deposit that handed this cash to the committee. Null + petugas = still with the petugas.
            $table->foreignId('setoran_id')->nullable()->after('petugas_id')->constrained('setoran')->nullOnDelete();

            $table->index(['petugas_id', 'setoran_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_iuran', function (Blueprint $table) {
            $table->dropIndex(['petugas_id', 'setoran_id']);
            $table->dropConstrainedForeignId('setoran_id');
            $table->dropConstrainedForeignId('petugas_id');
        });
    }
};
