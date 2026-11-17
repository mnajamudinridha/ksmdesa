<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The only way to grant master-panel access (is_super_admin is not mass-assignable).
 * Creates the account if the email is new, otherwise promotes the existing one.
 */
#[Signature('app:super-admin {email? : Email of the administrator}')]
#[Description('Create or promote a master (/admin) administrator')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email') ?? text('Email', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email tidak valid.');

        $user = User::query()->firstWhere('email', $email);

        if ($user === null) {
            $user = new User([
                'name' => text('Nama', required: true),
                'email' => $email,
                'password' => password('Kata sandi (min. 10 karakter)', required: true, validate: fn (string $v) => strlen($v) >= 10 ? null : 'Minimal 10 karakter.'),
            ]);
        }

        $user->is_super_admin = true;
        $user->save();

        $this->components->info("{$user->email} kini administrator master: ".url('/admin'));

        return self::SUCCESS;
    }
}
