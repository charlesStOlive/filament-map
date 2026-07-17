<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes;

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\CreateGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\EditGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\ListGeoPointTypes;
use CharlesStOlive\FilamentMap\Models\GeoPointType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GeoPointTypeResource extends Resource
{
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['attach-media'];
    protected static ?string $model = GeoPointType::class;

    protected static ?string $cluster = MapCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Type de point')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')->required()->unique(ignoreRecord: true),
                    TextInput::make('icon')->helperText('Ex: heroicon-o-map-pin, lucide-camera ou cle custom.'),
                    ColorPicker::make('color'),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_active')->default(true),
                    Textarea::make('description')->columnSpanFull(),
                ]),
            Section::make('Rendu par defaut')
                ->schema([
                    KeyValue::make('marker_style'),
                    KeyValue::make('options'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('icon')->toggleable(),
                ColorColumn::make('color'),
                TextColumn::make('points_count')->counts('points')->label('Points'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeoPointTypes::route('/'),
            'create' => CreateGeoPointType::route('/create'),
            'edit' => EditGeoPointType::route('/{record}/edit'),
        ];
    }
}
