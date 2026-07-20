<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\Maps;

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapViewportPicker;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages\CreateMap;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages\EditMap;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages\ListMaps;
use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MapResource extends Resource
{
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['preview', 'attach-point', 'detach-point'];
    protected static ?string $model = Map::class;

    protected static ?string $cluster = MapCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Carte')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Select::make('mode')
                        ->options([
                            'geojson' => 'GeoJSON stylise',
                            'openstreetmap' => 'OpenStreetMap',
                            'hybrid' => 'Hybride',
                            'svg_overlay' => 'SVG georeference',
                        ])
                        ->default('geojson')
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ]),
            Section::make('Vue initiale')
                ->columns(3)
                ->schema([
                    TextInput::make('center_latitude')->numeric()->step('0.0000001')->live(onBlur: true),
                    TextInput::make('center_longitude')->numeric()->step('0.0000001')->live(onBlur: true),
                    TextInput::make('zoom')->numeric()->minValue(0)->maxValue(22)->live(onBlur: true),
                    TextInput::make('min_zoom')->numeric()->minValue(0)->maxValue(22),
                    TextInput::make('max_zoom')->numeric()->minValue(0)->maxValue(22),
                    MapViewportPicker::make('viewport_picker')
                        ->label('Vue interactive')
                        ->dehydrated(false)
                        ->latitudeField('center_latitude')
                        ->longitudeField('center_longitude')
                        ->zoomField('zoom')
                        ->boundsField('bounds')
                        ->columnSpanFull(),
                ]),
            Section::make('Couches de la carte')
                ->schema([
                    Repeater::make('layerAssignments')
                        ->label('Layers')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->schema([
                            Select::make('map_layer_id')
                                ->label('Layer')
                                ->relationship('layer', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->columnSpanFull(),
                            TextInput::make('sort_order')
                                ->label('Ordre')
                                ->numeric()
                                ->default(0),
                            Toggle::make('is_visible_by_default')
                                ->label('Visible')
                                ->default(true),
                            Textarea::make('style')
                                ->label('Override style JSON')
                                ->rows(5)
                                ->formatStateUsing(fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                ->dehydrateStateUsing(fn ($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null)),
                            Textarea::make('style_rules')
                                ->label('Override règles JSON')
                                ->rows(5)
                                ->formatStateUsing(fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                ->dehydrateStateUsing(fn ($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null)),
                            Textarea::make('options')
                                ->label('Override options JSON')
                                ->rows(5)
                                ->formatStateUsing(fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                ->dehydrateStateUsing(fn ($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null)),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter une couche')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => filled($state['map_layer_id'] ?? null)
                            ? MapLayer::query()->find($state['map_layer_id'])?->name
                            : null),
                ]),
            Section::make('Options')
                ->collapsed()
                ->schema([
                    CodeEditor::make('bounds')
                        ->label('Bounds JSON')
                        ->language(Language::Json)
                        ->wrap()
                        ->formatStateUsing(fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                        ->dehydrateStateUsing(fn ($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                        ->helperText('Zone visible de la carte, remplie par la vue interactive. Le centre et le zoom restent les valeurs de démarrage.'),
                    KeyValue::make('options'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('mode')->badge()->sortable(),
                TextColumn::make('layers_count')->counts('layers')->label('Couches'),
                TextColumn::make('points_count')->counts('points')->label('Points'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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
            'index' => ListMaps::route('/'),
            'create' => CreateMap::route('/create'),
            'edit' => EditMap::route('/{record}/edit'),
        ];
    }
}
