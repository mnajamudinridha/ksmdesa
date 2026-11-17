<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksm', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // DNS label: becomes <slug>.ksmdesa.my.id, so 63 chars max.
            $table->string('slug', 63)->unique();
            $table->string('desa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksm');
    }
};
