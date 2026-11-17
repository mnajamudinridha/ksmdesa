<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            // Paths on the public disk; one is picked at random for each login page view.
            $table->json('latar_login')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('ksm', function (Blueprint $table) {
            $table->dropColumn('latar_login');
        });
    }
};
