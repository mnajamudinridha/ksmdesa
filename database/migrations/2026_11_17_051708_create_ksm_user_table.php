<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksm_user', function (Blueprint $table) {
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['ksm_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksm_user');
    }
};
