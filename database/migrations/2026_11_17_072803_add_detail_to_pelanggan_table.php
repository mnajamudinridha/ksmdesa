<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a customer lives and who serves them. The phone number is deliberately NOT here:
 * it lives on the linked warga account (users.no_hp), the single source for login and contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->foreignId('petugas_id')->nullable()->after('tarif_layanan_id')->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('petugas_id')->constrained('users')->nullOnDelete();
            $table->text('alamat')->nullable()->after('iuran_bulanan');
            $table->string('rw', 10)->nullable()->after('rt');
            $table->string('patokan')->nullable()->after('rw');
            $table->decimal('latitude', 10, 7)->nullable()->after('patokan');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('catatan_pengangkutan')->nullable()->after('catatan');

            $table->index(['ksm_id', 'petugas_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropIndex(['ksm_id', 'petugas_id']);
            $table->dropConstrainedForeignId('petugas_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['alamat', 'rw', 'patokan', 'latitude', 'longitude', 'catatan_pengangkutan']);
        });
    }
};
