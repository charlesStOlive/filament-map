<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapScenes;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapViewportPicker;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\CreateMapScene;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\EditMapScene;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages\ListMapScenes;
use CharlesStOlive\FilamentMap\Livewire\MapViewer;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Models\MapScene;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
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

    public static function getDocumentation(): array|string
    {
        return ['map.scenes', 'map.cartes', 'map.couches'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Scène cartographique')->description('Préparez ici les couches du décor. Les points et les interactions restent dans les scénarios.')
                ->columns(2)->schema([
                    TextInput::make('name')->label('Nom')->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                    TextInput::make('slug')->label('Clé stable')->required()->unique(ignoreRecord: true),
                    Select::make('map_id')->label('Carte de référence')->relationship('map', 'name')
                        ->required()->searchable()->preload()->live(),
                    Toggle::make('is_active')->label('Active')->default(true),
                    Textarea::make('description')->columnSpanFull(),
                ]),
            Section::make('Couches de la scène')->schema([
                Repeater::make('layerAssignments')->label('Couches')->relationship()->orderColumn('sort_order')
                    ->schema([
                        Select::make('map_layer_id')->label('Couche de la bibliothèque')->relationship('layer', 'name')
                            ->required()->searchable()->preload()->disableOptionsWhenSelectedInSiblingRepeaterItems()->distinct()->columnSpanFull(),
                        Toggle::make('is_visible_by_default')->label('Visible à l’ouverture')->default(true),
                        static::jsonField('style', 'Style propre à cette scène'),
                        static::jsonField('style_rules', 'Règles de style propres à cette scène'),
                        static::jsonField('options', 'Options propres à cette scène'),
                    ])->defaultItems(0)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state) => MapLayer::find($state['map_layer_id'] ?? null)?->name)
                    ->addActionLabel('Utiliser une couche'),
            ]),
            Section::make('Cadrage initial')->description('Laisser vide pour reprendre celui de la carte. Les limites de zoom restent définies par la carte.')
                ->columns(3)->schema([
                    TextInput::make('center_latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)->live(onBlur: true),
                    TextInput::make('center_longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)->live(onBlur: true),
                    TextInput::make('zoom')->numeric()->minValue(0)->maxValue(22)->live(onBlur: true),
                    MapViewportPicker::make('viewport')->dehydrated(false)->syncBounds(false)
                        ->map(fn (callable $get) => $get('map_id'))->scene(fn (?MapScene $record) => $record)
                        ->columnSpanFull(),
                ]),
            Section::make('Aperçu enregistré')->schema([
                LivewireComponent::make(MapViewer::class, fn (?MapScene $record) => ['scene' => $record, 'showRefresh' => true])
                    ->key(fn (?MapScene $record) => 'scene-preview-'.($record?->id ?? 'new')),
            ])->visible(fn (?MapScene $record) => (bool) $record?->exists),
            Section::make('Options avancées')->collapsed()->schema([KeyValue::make('options')]),
        ]);
    }

    private static function jsonField(string $name, string $label): Textarea
    {
        return Textarea::make($name)->label($label)->rows(3)->rules(['nullable', 'json'])
            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? json_decode($state, true, flags: JSON_THROW_ON_ERROR) : null);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Scène')->searchable(),
            TextColumn::make('map.name')->label('Carte'),
            TextColumn::make('layers_count')->counts('layers')->label('Couches'),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMapScenes::route('/'), 'create' => CreateMapScene::route('/create'), 'edit' => EditMapScene::route('/{record}/edit')];
    }
}
