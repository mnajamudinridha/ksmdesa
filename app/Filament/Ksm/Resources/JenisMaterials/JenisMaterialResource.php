<?php

namespace App\Filament\Ksm\Resources\JenisMaterials;

use App\Enums\KelompokMaterial;
use App\Filament\Ksm\Resources\JenisMaterials\Pages\ManageJenisMaterials;
use App\Models\JenisMaterial;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Kinds of sorted material, each in a group (plastic, paper, metal…) for the composition reports. */
class JenisMaterialResource extends Resource
{
    protected static ?string $model = JenisMaterial::class;

    protected static ?string $tenantRelationshipName = 'jenisMaterial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = 'jenis material';

    protected static ?string $pluralModelLabel = 'jenis material';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->withCount('catatanMaterial')
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('mis. Plastik PET')
                    ->scopedUnique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Jenis material dengan nama ini sudah ada.']),
                Select::make('kelompok')
                    ->options(KelompokMaterial::class)
                    ->required(),
                Toggle::make('aktif')
                    ->default(true)
                    ->helperText('Jenis nonaktif tidak bisa dipilih untuk catatan baru.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->emptyStateHeading('Belum ada jenis material')
            ->emptyStateDescription('Contoh: Plastik PET, Plastik campur, Kardus, Kertas HVS, Kaleng, Botol kaca, Sisa makanan.')
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kelompok')
                    ->badge(),
                TextColumn::make('catatan_material_count')
                    ->label('Catatan')
                    ->numeric(),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kelompok')
                    ->options(KelompokMaterial::class),
                TernaryFilter::make('aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJenisMaterials::route('/'),
        ];
    }
}
