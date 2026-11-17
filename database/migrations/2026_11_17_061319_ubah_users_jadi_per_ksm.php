<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Users now belong to exactly one KSM (or none, for master administrators),
 * with a role inside it. Replaces the ksm_user pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ksm_id')->nullable()->after('id')->constrained('ksm')->cascadeOnDelete();
            $table->string('peran', 20)->nullable()->after('email');
            $table->string('no_hp', 20)->nullable()->after('peran');
            $table->boolean('aktif')->default(true)->after('is_super_admin');
        });

        // Pivot membership → users.ksm_id. Master administrators stay global.
        DB::table('ksm_user')
            ->orderBy('created_at')
            ->orderBy('ksm_id')
            ->get()
            ->groupBy('user_id')
            ->each(function ($baris, $userId): void {
                if (DB::table('users')->where('id', $userId)->value('is_super_admin')) {
                    return;
                }

                if ($baris->count() > 1) {
                    Log::warning("User {$userId} belonged to {$baris->count()} KSMs; kept KSM {$baris->first()->ksm_id}.");
                }

                DB::table('users')->where('id', $userId)->update([
                    'ksm_id' => $baris->first()->ksm_id,
                    'peran' => 'admin',
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->dropUnique('users_email_unique');
            $table->unique(['ksm_id', 'email'], 'users_ksm_email_unique');
            $table->unique(['ksm_id', 'no_hp'], 'users_ksm_no_hp_unique');
        });

        Schema::dropIfExists('ksm_user');
    }

    public function down(): void
    {
        Schema::create('ksm_user', function (Blueprint $table) {
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['ksm_id', 'user_id']);
        });

        DB::table('users')->whereNotNull('ksm_id')->get(['id', 'ksm_id'])->each(
            fn ($user) => DB::table('ksm_user')->insert([
                'ksm_id' => $user->ksm_id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_ksm_email_unique');
            $table->dropUnique('users_ksm_no_hp_unique');
            $table->dropConstrainedForeignId('ksm_id');
            $table->dropColumn(['peran', 'no_hp', 'aktif']);
            $table->unique('email');
        });
    }
};
