<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Bootstrap or recover a master administrator (is_super_admin is not mass-assignable).
 * Afterwards, administrators can add each other from /admin → Administrator.
 * Matches only global accounts (no KSM): a village user with the same email is untouched.
 */
#[Signature('app:super-admin {email? : Email of the administrator}')]
#[Description('Create or promote a master (/admin) administrator')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->argument('email') ?? text(
            'Email',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email tidak valid.',
        ))));

        $user = User::query()->whereNull('ksm_id')->where('email', $email)->first();

        if ($user === null) {
            $user = new User([
                'name' => text('Nama', required: true),
                'email' => $email,
                'password' => password('Kata sandi (min. 8 karakter)', required: true, validate: fn (string $v) => strlen($v) >= 8 ? null : 'Minimal 8 karakter.'),
            ]);
        }

        $user->forceFill(['is_super_admin' => true, 'aktif' => true, 'peran' => null])->save();

        $this->components->info("{$user->email} kini administrator master: ".url('/admin'));

        return self::SUCCESS;
    }
}
