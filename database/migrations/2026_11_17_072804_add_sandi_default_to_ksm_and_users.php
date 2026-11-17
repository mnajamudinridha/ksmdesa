<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default password for auto-created warga accounts, per KSM (stored as a hash only),
 * and a flag marking accounts that still use it — those follow when the default changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            $table->string('sandi_default_hash')->nullable()->after('logo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pakai_sandi_default')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            $table->dropColumn('sandi_default_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pakai_sandi_default');
        });
    }
};
