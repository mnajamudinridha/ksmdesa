<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->date('tanggal');
            // pilah | jual | olah (App\Enums\AktivitasMaterial)
            $table->string('aktivitas', 10);
            // Null-on-delete, not restrict: deleting a KSM cascades through all of its tables. The
            // policies keep used master rows.
            $table->foreignId('jenis_material_id')->nullable()->constrained('jenis_material')->nullOnDelete();
            $table->decimal('berat_kg', 9, 1);
            // The buyer (a partner of kind "pengepul"), sales only.
            $table->foreignId('mitra_id')->nullable()->constrained('mitra')->nullOnDelete();
            // Rupiah, sales only. Counted as cash income on `tanggal` — never copied into the cash book.
            $table->unsignedBigInteger('nilai_jual')->nullable();
            $table->string('hasil_olah')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ksm_id', 'tanggal']);
            $table->index(['ksm_id', 'aktivitas', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_material');
    }
};
