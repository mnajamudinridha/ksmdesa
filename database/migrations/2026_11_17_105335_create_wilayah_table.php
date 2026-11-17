<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->unsignedSmallInteger('rt');
            // Many villages (especially outside Java) have no RW, only a dusun/lingkungan.
            $table->unsignedSmallInteger('rw')->nullable();
            $table->string('dusun', 100)->nullable();
            // Households in the RT, for the share of households that subscribe.
            $table->unsignedInteger('jumlah_kk')->nullable();
            $table->timestamps();

            // NULL rw is not unique in the database (NULL ≠ NULL): the form's scoped rule guards that case.
            $table->unique(['ksm_id', 'rw', 'rt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
