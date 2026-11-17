<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A single row: the master panel's own logo and login backgrounds (App\Models\PengaturanMaster). */
    public function up(): void
    {
        Schema::create('pengaturan_master', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable();
            $table->json('latar_login')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_master');
    }
};
