<?php

namespace App\Filament\Ksm\Pages\Auth;

use App\Support\KsmDariHost;
use App\Support\NomorHp;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Login on a KSM subdomain. Village users sign in with their phone number, matched
 * only inside the KSM of this subdomain (the same number may exist in another KSM as a
 * separate account). An email address is accepted for master administrators only.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('login')
                    ->label('Nomor HP')
                    ->helperText('Administrator pusat: gunakan email.')
                    ->required()
                    ->maxLength(255)
                    ->autocomplete('username')
                    ->autofocus(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    /**
     * EloquentUserProvider applies closures in the credentials as query constraints.
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['login']);

        if (str_contains($login, '@')) {
            $email = mb_strtolower($login);

            return [
                'password' => $data['password'],
                fn (Builder $query) => $query->where('email', $email)->where('is_super_admin', true)->whereNull('ksm_id'),
            ];
        }

        $nomor = NomorHp::normalisasi($login);
        $ksmId = KsmDariHost::dariRequest()?->getKey();

        return [
            'password' => $data['password'],
            // where('no_hp', null) would turn into whereNull and match users without a phone number.
            fn (Builder $query) => ($nomor === null || $ksmId === null)
                ? $query->whereRaw('1 = 0')
                : $query->where('no_hp', $nomor)->where('ksm_id', $ksmId),
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => 'Nomor HP/email atau kata sandi salah.',
        ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return KsmDariHost::dariRequest()?->nama ?? parent::getHeading();
    }
}
