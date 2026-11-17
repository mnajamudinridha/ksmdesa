<?php

namespace App\Models;

use App\Models\Concerns\PunyaTampilanLogin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The master panel's look (ksmdesa.my.id/admin): its logo and login backgrounds.
 * A single row, written only by the "Tampilan" page; reading never creates it.
 */
#[Table('pengaturan_master')]
#[Fillable(['logo', 'latar_login'])]
class PengaturanMaster extends Model
{
    use PunyaTampilanLogin;

    public static function sekarang(): self
    {
        return static::query()->oldest('id')->first() ?? new static;
    }
}
