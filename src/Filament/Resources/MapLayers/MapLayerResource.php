<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers;

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\CreateMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\EditMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\ListMapLayers;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MapLayerResource extends Resource
{
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['import', 'export', 'preview'];
    protected static ?string $model = MapLayer::class;

    protected static ?string $cluster = MapCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Couche')
                ->columns(2)
                ->schema([
                    Select::make('map_id')->relationship('map', 'name')->required()->searchable()->preload(),
                    TextInput::make('name')->label('Nom')->required(),
                    TextInput::make('key')->required(),
                    Select::make('type')
                        ->options([
                            'geojson' => 'GeoJSON',
                            'tile' => 'Tuiles',
                            'points' => 'Points',
                            'svg_overlay' => 'SVG overlay',
                            'custom' => 'Custom',
                        ])
                        ->default('geojson')
                        ->required(),
                    Select::make('source_type')
                        ->options([
                            'url' => 'URL',
                            'path' => 'Chemin publie',
                            'json' => 'JSON',
                            'media' => 'Media Library',
                        ]),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_visible_by_default')->default(true),
                    Toggle::make('is_active')->default(true),
                ]),
            Section::make('Source')
                ->schema([
                    TextInput::make('source_url')->url(),
                    TextInput::make('source_path'),
                    Textarea::make('source_json')->rows(8),
                ]),
            Section::make('Style et options')
                ->schema([
                    KeyValue::make('style'),
                    KeyValue::make('style_rules'),
                    KeyValue::make('options'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('map.name')->label('Carte')->searchable()->sortable(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_visible_by_default')->boolean(),
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
            'index' => ListMapLayers::route('/'),
            'create' => CreateMapLayer::route('/create'),
            'edit' => EditMapLayer::route('/{record}/edit'),
        ];
    }
}
