<?php

namespace App\Filament\Resources\Ksms;

use App\Filament\Resources\Ksms\Pages\CreateKsm;
use App\Filament\Resources\Ksms\Pages\EditKsm;
use App\Filament\Resources\Ksms\Pages\ListKsms;
use App\Filament\Resources\Ksms\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Ksms\Schemas\KsmForm;
use App\Filament\Resources\Ksms\Tables\KsmsTable;
use App\Models\Ksm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KsmResource extends Resource
{
    protected static ?string $model = Ksm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $modelLabel = 'KSM';

    protected static ?string $pluralModelLabel = 'KSM';

    public static function form(Schema $schema): Schema
    {
        return KsmForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KsmsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKsms::route('/'),
            'create' => CreateKsm::route('/create'),
            'edit' => EditKsm::route('/{record}/edit'),
        ];
    }
}
