<?php

namespace App\Filament\Pages;

use App\Filament\Shared\LatarLoginField;
use App\Filament\Shared\LogoKsmField;
use App\Models\PengaturanMaster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Master panel look: the logo shown in the master panel and on its login page, and the
 * login page backgrounds. Every KSM sets its own in Profil KSM.
 *
 * @property-read Schema $form
 */
class TampilanMaster extends Page
{
    protected string $view = 'filament.pages.tampilan-master';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Tampilan';

    protected static ?string $title = 'Tampilan master';

    protected static ?string $slug = 'tampilan';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(PengaturanMaster::sekarang()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            // Bound to the record so preventFilePathTampering() recognises the stored files.
            ->model(PengaturanMaster::sekarang())
            ->statePath('data')
            ->components([
                Section::make('Logo & halaman login')
                    ->description('Logo tampil di halaman login master dan di samping nama panel; latar menghiasi halaman login master. Tiap KSM mengatur miliknya di Profil KSM.')
                    ->schema([
                        LogoKsmField::make('logo-master', 'Logo master'),
                        LatarLoginField::make(),
                    ]),
            ]);
    }

    public function simpan(): void
    {
        PengaturanMaster::sekarang()->fill($this->form->getState())->save();

        Notification::make()->success()->title('Tampilan master tersimpan')->send();
    }
}
