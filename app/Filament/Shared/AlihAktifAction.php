<?php

namespace App\Filament\Shared;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Deactivate / reactivate an account instead of deleting it: an inactive user cannot
 * sign in (User::canAccessPanel) but their history stays. Never offered on your own row.
 */
final class AlihAktifAction
{
    public static function make(): Action
    {
        return Action::make('alihAktif')
            ->label(fn (User $record): string => $record->aktif ? 'Nonaktifkan' : 'Aktifkan')
            ->icon(fn (User $record): Heroicon => $record->aktif ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (User $record): string => $record->aktif ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->aktif
                ? "{$record->name} tidak akan bisa login lagi. Riwayatnya tetap tersimpan."
                : "{$record->name} bisa login lagi.")
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->authorize('update')
            ->action(function (User $record): void {
                abort_if($record->is(auth()->user()), 403);

                $record->update(['aktif' => ! $record->aktif]);
            });
    }
}
