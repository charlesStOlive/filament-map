<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapScenes;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapPositionInput;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\CreateMapScene;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\EditMapScene;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\ListMapScenes;
use CharlesStOlive\FilamentMap\Livewire\MapViewer;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Models\MapSceneLayerAssignment;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MapSceneResource extends Resource implements HasKnowledgeBase
{
    use BelongsToConfiguredMapCluster;
    use HasMapResourceAuthorization;

    protected static ?string $model = MapScene::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Scènes cartographiques';

    protected static ?string $modelLabel = 'scène cartographique';

    protected static ?string $pluralModelLabel = 'scènes cartographiques';

    /** Le format des vignettes : 16/9, sans repère (voir MapPositionInput::thumbnailField()). */
    public const THUMBNAIL_WIDTH = 480;

    public const THUMBNAIL_HEIGHT = 270;

    public static function getDocumentation(): array|string
    {
        return ['map.scenes', 'map.couches'];
    }

    /**
     * Dans l'ordre où une scène se compose : ce qu'elle est, ses couches, puis sa vue initiale — dont « Valider » prend la
     * vignette, avec les couches choisies juste au-dessus (voir draftScene()). Le reste (aperçu enregistré, options
     * avancées) est replié.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Scène cartographique')
                ->description('Préparez ici les couches du décor. Les points et les interactions restent dans les scénarios.')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextInput::make('name')->label('Nom')->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null)
                        ->columnSpan(['xl' => 2]),
                    TextInput::make('slug')->label('Clé stable')->required()->unique(ignoreRecord: true),
                    Select::make('mode')
                        ->options([
                            'geojson' => 'GeoJSON stylisé',
                            'openstreetmap' => 'OpenStreetMap',
                            'hybrid' => 'Hybride',
                            'svg_overlay' => 'SVG géoréférencé',
                        ])
                        ->default('geojson')
                        ->required(),
                    Textarea::make('description')->rows(2)->columnSpan(['md' => 2, 'xl' => 3]),
                    Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                ]),
            Section::make('Couches de la scène')
                ->description('Dans l’ordre d’affichage. Une couche masquée à l’ouverture reste proposée dans le sélecteur de couches de la carte.')
                ->schema([
                    Repeater::make('layerAssignments')->hiddenLabel()->relationship()->orderColumn('sort_order')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                // Suivis en direct : la carte de la vue initiale (et donc la vignette) les reprend aussitôt.
                                Select::make('map_layer_id')->label('Couche de la bibliothèque')->relationship('layer', 'name')
                                    ->required()->searchable()->preload()->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()->distinct()
                                    ->columnSpan(['md' => 2]),
                                Toggle::make('is_visible_by_default')->label('Visible à l’ouverture')->default(true)->live()->inline(false),
                            ]),
                            Section::make('Réglages propres à cette scène')
                                ->description('Style, règles et options qui remplacent ceux de la couche, ici seulement (JSON).')
                                ->collapsed()->compact()
                                ->columns(['default' => 1, 'lg' => 3])
                                ->schema([
                                    static::jsonField('style', 'Style'),
                                    static::jsonField('style_rules', 'Règles de style'),
                                    static::jsonField('options', 'Options'),
                                ]),
                        ])->defaultItems(0)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => MapLayer::find($state['map_layer_id'] ?? null)?->name)
                        ->addActionLabel('Utiliser une couche'),
                ]),
            Section::make('Vue initiale et vignette')
                ->description('Posez le repère au centre de la vue et réglez le zoom : « Valider » prend aussi la vignette de la scène, avec les couches visibles ci-dessus. Elle la représente dans la liste des scènes et là où on choisit une scène.')
                ->schema([
                    // Le résumé s'affiche ici ; le formulaire (repère, coordonnées, adresse, zoom, zoom min et max) est dans le
                    // popup, qui ne reporte rien avant « Valider ».
                    MapPositionInput::make()
                        ->label('Vue initiale')
                        ->latitudeField('center_latitude')->longitudeField('center_longitude')
                        ->zoomField('zoom', 'Zoom')
                        ->minZoomField('min_zoom', 'Zoom minimum')
                        ->maxZoomField('max_zoom', 'Zoom maximum')
                        ->thumbnailField('thumbnail', width: self::THUMBNAIL_WIDTH, height: self::THUMBNAIL_HEIGHT, marker: false)
                        ->scene(fn (?MapScene $record, Get $get): MapScene => static::draftScene($record, $get)),
                ]),
            Section::make('Aperçu enregistré')
                ->description('La scène telle qu’elle est enregistrée, avec ses points.')
                ->collapsible()->collapsed()
                ->schema([
                    LivewireComponent::make(MapViewer::class, fn (?MapScene $record) => ['scene' => $record, 'showRefresh' => true])
                        ->key(fn (?MapScene $record) => 'scene-preview-'.($record?->id ?? 'new')),
                ])->visible(fn (?MapScene $record) => (bool) $record?->exists),
            Section::make('Options avancées')->collapsed()->schema([
                CodeEditor::make('bounds')
                    ->label('Bounds JSON')
                    ->language(Language::Json)
                    ->wrap()
                    ->formatStateUsing(fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                    ->dehydrateStateUsing(fn ($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
                    ->helperText('Zone visible de la scène, remplie par la vue interactive.'),
                KeyValue::make('options'),
            ]),
        ]);
    }

    /**
     * La scène telle que le formulaire la compose, avant tout enregistrement : la carte de la vue initiale (et sa vignette)
     * montre les couches choisies dans le formulaire, dans leur ordre et leur visibilité, pas celles enregistrées.
     */
    public static function draftScene(?MapScene $record, Get $get): MapScene
    {
        $bounds = $get('bounds');

        $scene = new MapScene([
            'name' => $get('name'),
            'slug' => $get('slug'),
            'mode' => $get('mode'),
            'center_latitude' => $get('center_latitude'),
            'center_longitude' => $get('center_longitude'),
            'zoom' => $get('zoom'),
            'min_zoom' => $get('min_zoom'),
            'max_zoom' => $get('max_zoom'),
            'bounds' => is_string($bounds) ? json_decode($bounds, true) : $bounds,
            'is_active' => true,
        ]);
        $scene->id = $record?->getKey();

        return $scene->setRelation('layers', static::draftLayers(array_values((array) $get('layerAssignments'))));
    }

    /**
     * Les couches choisies dans le formulaire, chacune avec son réglage propre à la scène (ce que le constructeur de la
     * carte lit dans le pivot) : dans l'ordre du formulaire, les couches pas encore choisies en moins.
     *
     * @param  array<int, array<string, mixed>>  $assignments
     * @return Collection<int, MapLayer>
     */
    protected static function draftLayers(array $assignments): Collection
    {
        $layers = MapLayer::query()
            ->whereKey(array_filter(array_column($assignments, 'map_layer_id')))
            ->get()
            ->keyBy(fn (MapLayer $layer): int => (int) $layer->getKey());

        return collect($assignments)
            ->map(function (array $assignment, int $index) use ($layers): ?MapLayer {
                $layer = $layers->get((int) ($assignment['map_layer_id'] ?? 0));

                // Une couche n'est choisie qu'une fois par scène (->distinct()) : son instance peut porter ce pivot-là.
                return $layer?->setRelation('pivot', new MapSceneLayerAssignment([
                    'sort_order' => $index,
                    'is_visible_by_default' => (bool) ($assignment['is_visible_by_default'] ?? true),
                    'style' => $assignment['style'] ?? null,
                    'style_rules' => $assignment['style_rules'] ?? null,
                    'options' => $assignment['options'] ?? null,
                ]));
            })
            ->filter()
            ->values();
    }

    private static function jsonField(string $name, string $label): Textarea
    {
        return Textarea::make($name)->label($label)->rows(3)->rules(['nullable', 'json'])
            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? json_decode($state, true, flags: JSON_THROW_ON_ERROR) : null);
    }

    /**
     * Une liste plutôt qu'un tableau : chaque scène sur une ligne souple (Split), sa vignette en tête, son nom et sa
     * description empilés (Stack), puis ce qui la décrit. Sous `md`, les blocs s'empilent.
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Split::make([
                ImageColumn::make('thumbnail')
                    ->label('Vignette')
                    ->imageWidth(self::THUMBNAIL_WIDTH / 3)
                    ->imageHeight(self::THUMBNAIL_HEIGHT / 3)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover', 'alt' => 'Vignette de la scène'])
                    ->defaultImageUrl(fn (): string => static::emptyThumbnail())
                    ->grow(false),
                Stack::make([
                    TextColumn::make('name')->label('Scène')->weight(FontWeight::SemiBold)->searchable()->sortable(),
                    TextColumn::make('description')->color('gray')->limit(120)->placeholder('Sans description'),
                ])->space(1),
                Stack::make([
                    TextColumn::make('mode')->badge(),
                    TextColumn::make('layers_count')->counts('layers')->label('Couches')->color('gray')
                        ->formatStateUsing(fn (int $state): string => $state.' '.($state > 1 ? 'couches' : 'couche')),
                ])->space(1)->alignment(Alignment::End)->grow(false),
                IconColumn::make('is_active')->label('Active')->boolean()->grow(false),
            ])->from('md'),
        ])->recordActions([EditAction::make()]);
    }

    /** Le cadre d'une scène sans vignette : gris, au format des vignettes, pour que la liste reste alignée. */
    protected static function emptyThumbnail(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::THUMBNAIL_WIDTH.'" height="'.self::THUMBNAIL_HEIGHT.'"><rect width="100%" height="100%" fill="#e5e7eb"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function getPages(): array
    {
        return ['index' => ListMapScenes::route('/'), 'create' => CreateMapScene::route('/create'), 'edit' => EditMapScene::route('/{record}/edit')];
    }
}
