<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash book start: the cash on hand (saldo_awal) on the morning of kas_mulai. Without a date the
 * balance counts from the first record ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            $table->unsignedBigInteger('saldo_awal')->default(0)->after('sandi_default_hash');
            $table->date('kas_mulai')->nullable()->after('saldo_awal');
        });
    }

    public function down(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            $table->dropColumn(['saldo_awal', 'kas_mulai']);
        });
    }
};
