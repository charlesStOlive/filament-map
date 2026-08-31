<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapLayerPreview;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\CreateMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\EditMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\ListMapLayers;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MapLayerResource extends Resource
{
    use BelongsToConfiguredMapCluster;
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['import', 'export', 'preview'];

    protected static ?string $model = MapLayer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Couches cartographiques';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Couche')
                ->columns(2)
                ->schema([
                    Select::make('preview_map_id')
                        ->label('Carte d’exemple')
                        ->relationship('previewMap', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('Utilisée uniquement pour prévisualiser la couche.'),
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Select::make('type')
                        ->options([
                            'geojson' => 'GeoJSON',
                            'tile' => 'Tuiles',
                            'points' => 'Points',
                            'svg_overlay' => 'SVG overlay',
                            'custom' => 'Custom',
                        ])
                        ->default('geojson')
                        ->required()
                        ->live(),
                    Select::make('source_type')
                        ->label('Type de source')
                        ->options([
                            'url' => 'URL',
                            'json' => 'JSON brut',
                            'file' => 'Fichier uploadé',
                        ])
                        ->default('url')
                        ->required()
                        ->live(),
                    Toggle::make('is_active')->default(true),
                    TextInput::make('source_url')
                        ->label('URL source')
                        ->helperText('URL distante ou URL publique Laravel, par exemple /storage/maps/asie-sudest.geojson. Les templates Leaflet {z}/{x}/{y}{r} sont acceptés.')
                        ->visible(fn(Get $get): bool => $get('source_type') === 'url')
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    FileUpload::make('source_path')
                        ->label('Fichier source')
                        ->disk(config('filament-map.files.disk', 'public'))
                        ->directory(config('filament-map.files.directory', 'filament-map/layers'))
                        ->visibility('public')
                        ->acceptedFileTypes([
                            'application/json',
                            'application/geo+json',
                            'application/octet-stream',
                            'text/json',
                            'text/plain',
                            'image/svg+xml',
                        ])
                        ->downloadable()
                        ->openable()
                        ->visible(fn(Get $get): bool => $get('source_type') === 'file')
                        ->live()
                        ->columnSpanFull(),
                    Textarea::make('source_json')
                        ->label('Source JSON')
                        ->helperText('GeoJSON collé directement. Pratique pour tester, moins adapté aux gros fichiers.')
                        ->rows(12)
                        ->formatStateUsing(fn($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                        ->dehydrateStateUsing(fn($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                        ->visible(fn(Get $get): bool => $get('source_type') === 'json')
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                ]),
            Section::make('Style et options')
                ->columns(3)
                ->schema([
                    Textarea::make('style')
                        ->label('Style JSON')
                        ->rows(10)
                        ->formatStateUsing(fn($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                        ->dehydrateStateUsing(fn($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                        ->helperText('Style Leaflet appliqué par défaut à toutes les entités GeoJSON de la couche.')
                        ->live(onBlur: true),
                    Textarea::make('style_rules')
                        ->label('Règles JSON')
                        ->rows(10)
                        ->formatStateUsing(fn($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                        ->dehydrateStateUsing(fn($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                        ->helperText('Overrides de style selon les properties GeoJSON, par exemple ADM0_A3 = KHM.')
                        ->live(onBlur: true),
                    Textarea::make('options')
                        ->label('Options JSON')
                        ->rows(10)
                        ->formatStateUsing(fn($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                        ->dehydrateStateUsing(fn($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                        ->helperText('Options techniques Leaflet. À laisser vide pour une simple coloration GeoJSON.')
                        ->live(onBlur: true),
                ]),
            Section::make('Aperçu')
                ->schema([
                    MapLayerPreview::make('layer_preview')
                        ->label('Preview du layer')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('previewMap.name')->label('Carte d’exemple')->toggleable(),
                TextColumn::make('type')->badge()->sortable(),
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
