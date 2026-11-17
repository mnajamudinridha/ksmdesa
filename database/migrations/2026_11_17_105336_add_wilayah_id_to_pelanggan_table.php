<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers pick their RT from the KSM's list (wilayah). Additive only: the old free-text
 * rt/rw columns stay untouched (no longer used by the app) so no existing value can be lost;
 * drop them in a later migration once production is confirmed to hold none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()->after('tarif_layanan_id')->constrained('wilayah')->nullOnDelete();
            $table->index(['ksm_id', 'wilayah_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropIndex(['ksm_id', 'wilayah_id']);
            $table->dropConstrainedForeignId('wilayah_id');
        });
    }
};
