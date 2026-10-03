<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MarkerPreview;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\CreateGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\EditGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\ListGeoPointTypes;
use CharlesStOlive\FilamentMap\Models\GeoPointType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use CharlesStOlive\FilamentMap\Support\MapPermissions;
use CharlesStOlive\FilamentMap\Support\MarkerShapes;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GeoPointTypeResource extends Resource implements HasKnowledgeBase
{
    use BelongsToConfiguredMapCluster;

    /**
     * Action propre, déclarée au format de charlesstolive/filament-permission-manager, sans en dépendre (voir
     * Support\MapPermissions) : joindre ou changer l'image de marqueur par défaut du type.
     *
     * @var array<int, string>
     */
    public static array $specificPermissions = ['attach-media'];

    /** @var array<string, string> Son libellé dans l'écran des rôles. */
    protected static array $permissionLabels = ['attach-media' => 'Joindre ou changer l’image de marqueur par défaut'];

    protected static ?string $model = GeoPointType::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Types de points';

    public static function getDocumentation(): array|string
    {
        return ['map.types-de-points', 'map.apparence'];
    }

    /**
     * Les réglages à gauche, l'aperçu du marqueur à droite (MarkerPreview), qui suit la saisie : les champs qu'il lit
     * sont `live()`.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Group::make([
                Section::make('Type de point')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                        TextInput::make('key')->required()->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->label('Icône')
                            ->live(onBlur: true)
                            ->helperText('Ex : heroicon-o-map-pin. Montrée quand le contenu est « Icône », ou à défaut d’image.'),
                        ColorPicker::make('color')->label('Couleur')->live(),
                        TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
                        Toggle::make('is_active')->label('Actif')->default(true),
                        Textarea::make('description')->columnSpanFull(),
                    ]),
                Section::make('Rendu par défaut')
                    ->description('La forme du marqueur et ce que sa zone de contenu montre. L’aperçu, à droite, suit vos réglages.')
                    ->columns(2)
                    ->statePath('marker_style')
                    ->schema([
                        Select::make('shape')
                            ->label('Forme')
                            ->options(MarkerShapes::LABELS)
                            ->default('pin')
                            ->required()
                            ->live(),
                        Select::make('content.type')
                            ->label('Contenu de la zone')
                            ->options([
                                'none' => 'Aucun : la forme seule',
                                'icon' => 'Icône',
                                'image' => 'Image (mini-vignette)',
                                'text' => 'Texte',
                            ])
                            ->default('icon')
                            ->required()
                            ->live(),
                        Textarea::make('svg')
                            ->label('SVG personnalisé')
                            ->visible(fn (Get $get): bool => $get('shape') === 'svg')
                            // Gardé quand on essaie une autre forme : on peut y revenir sans le perdre.
                            ->dehydratedWhenHidden()
                            ->live(debounce: 600)
                            ->helperText('Avec une viewBox. currentColor prend la couleur du point. La zone qui reçoit l’image, l’icône ou le texte est un circle, une ellipse ou un rect marqué data-slot (il n’est pas dessiné) ; sans elle, la forme reste seule. data-anchor="bottom" sur la balise svg pose sa base sur la position (centre par défaut). Scripts, contenus embarqués et liens externes sont retirés ; un SVG illisible laisse place à l’épingle.')
                            ->rows(6)
                            ->columnSpanFull(),
                        TextInput::make('content.value')
                            ->label(fn (Get $get): string => $get('content.type') === 'text' ? 'Texte' : 'Icône de la zone')
                            ->visible(fn (Get $get): bool => in_array($get('content.type'), ['icon', 'text'], true))
                            ->dehydratedWhenHidden()
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): string => $get('content.type') === 'text'
                                ? 'Court : un numéro, deux lettres.'
                                : 'Laissée vide, l’icône du type.'),
                        Slider::make('size')
                            ->label('Taille')
                            ->range(MarkerShapes::MIN_PERCENT, MarkerShapes::MAX_PERCENT)
                            ->step(5)
                            ->default(100)
                            ->tooltips(RawJs::make('`${Math.round($value)} %`'))
                            ->formatStateUsing(fn (mixed $state): float => MarkerShapes::percent($state))
                            ->live()
                            ->helperText('En % de la taille standard : '.MarkerShapes::STANDARD_SIZE.' px sur le plus grand côté, celle de l’épingle. L’autre côté suit les proportions de la forme.')
                            ->columnSpanFull(),
                        Select::make('anchor')
                            ->label('Ancrage')
                            ->options([
                                'center' => 'Centre',
                                'bottom' => 'Bas (une pointe)',
                                'top' => 'Haut',
                                'left' => 'Gauche',
                                'right' => 'Droite',
                                'bottom-left' => 'Bas gauche',
                                'bottom-right' => 'Bas droite',
                                'top-left' => 'Haut gauche',
                                'top-right' => 'Haut droite',
                            ])
                            ->placeholder('Celui de la forme')
                            ->live()
                            ->helperText('Le point de la forme posé sur la position (la croix rouge de l’aperçu). Vide : celui de la forme — la pointe d’une épingle, le data-anchor d’un SVG, sinon le centre.'),
                        Slider::make('rotation')
                            ->label('Rotation')
                            ->range(-180, 180)
                            ->step(5)
                            ->default(0)
                            ->tooltips(RawJs::make('`${Math.round($value)}°`'))
                            ->formatStateUsing(fn (mixed $state): float => is_numeric($state) ? (float) $state : 0)
                            ->live()
                            ->helperText('En degrés, autour de l’ancrage.'),
                        Slider::make('offset.x')
                            ->label('Décalage horizontal')
                            ->range(-MarkerShapes::MAX_OFFSET, MarkerShapes::MAX_OFFSET)
                            ->step(5)
                            ->default(0)
                            ->tooltips(RawJs::make('`${Math.round($value)} %`'))
                            ->formatStateUsing(fn (mixed $state): float => is_numeric($state) ? (float) $state : 0)
                            ->live()
                            ->helperText('En % de la largeur du marqueur, vers la droite : il suit la taille.'),
                        Slider::make('offset.y')
                            ->label('Décalage vertical')
                            ->range(-MarkerShapes::MAX_OFFSET, MarkerShapes::MAX_OFFSET)
                            ->step(5)
                            ->default(0)
                            ->tooltips(RawJs::make('`${Math.round($value)} %`'))
                            ->formatStateUsing(fn (mixed $state): float => is_numeric($state) ? (float) $state : 0)
                            ->live()
                            ->helperText('En % de sa hauteur, vers le bas.'),
                        KeyValue::make('css')
                            ->label('Variables de style')
                            ->live(onBlur: true)
                            ->helperText('Ex : --filament-map-marker-content-color (couleur de l’icône ou du texte), opacity.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Options avancées')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('default_marker_image')
                            ->disabled(fn (): bool => ! MapPermissions::allows(static::class, 'attach-media'))
                            ->label('Image par défaut')
                            ->helperText('Pour un contenu « Image » : montrée quand ni le point ni le parcours n’en donnent une.')
                            ->collection(config('filament-map.media_collections.default_marker_image', 'default_marker_image'))
                            ->image()
                            ->columnSpanFull(),
                        KeyValue::make('options'),
                    ]),
            ])->columnSpan(['default' => 1, 'lg' => 2]),
            Section::make('Aperçu')
                ->schema([MarkerPreview::make()])
                ->columnSpan(1)
                ->extraAttributes(['class' => 'lg:sticky lg:top-20 lg:self-start']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // L'image par défaut de chaque type, pour l'aperçu de son marqueur.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->columns([
                // Le marqueur tel que la carte le dessine, et ce qu'il accepte (voir MarkerPreview).
                ViewColumn::make('marker')->label('Marqueur')->view('filament-map::tables.columns.marker-preview'),
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('key')->label('Clé')->searchable()->sortable(),
                TextColumn::make('icon')->label('Icône')->toggleable(isToggledHiddenByDefault: true),
                ColorColumn::make('color')->label('Couleur')->toggleable(isToggledHiddenByDefault: true),
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
