<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\IconPicker;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MarkerPreview;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\CreateGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\EditGeoPointType;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages\ListGeoPointTypes;
use CharlesStOlive\FilamentMap\Models\GeoPointType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use CharlesStOlive\FilamentMap\Support\MapPermissions;
use CharlesStOlive\FilamentMap\Support\MarkerShapes;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
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
     * Les réglages à gauche, en trois temps — la forme, ce que montre sa zone de contenu, les options —, l'aperçu du
     * marqueur à droite (MarkerPreview), qui suit la saisie : les champs qu'il lit sont `live()`.
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
                        TextInput::make('key')->label('Clé')->required()->unique(ignoreRecord: true),
                        ColorPicker::make('color')
                            ->label('Couleur')
                            ->live()
                            ->helperText('Celle de la forme. Un parcours peut la remplacer pour ses points.'),
                        TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
                        Toggle::make('is_active')->label('Actif')->default(true),
                        Textarea::make('description')->columnSpanFull(),
                    ]),
                static::shapeSection(),
                static::contentSection(),
                Section::make('Options avancées')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('marker_style.css')
                            ->label('Variables de style')
                            ->live(onBlur: true)
                            ->helperText('Ex : --filament-map-marker-content-color (couleur de l’icône ou du texte), opacity.'),
                        KeyValue::make('options'),
                    ]),
            ])->columnSpan(['default' => 1, 'lg' => 2]),
            Section::make('Aperçu')
                ->schema([MarkerPreview::make()])
                ->columnSpan(1)
                ->extraAttributes(['class' => 'lg:sticky lg:top-20 lg:self-start']),
        ]);
    }

    /** La forme du marqueur, sa taille et sa position. */
    protected static function shapeSection(): Section
    {
        $percentTooltip = RawJs::make('`${Math.round($value)} %`');
        $number = fn (mixed $state): float => is_numeric($state) ? (float) $state : 0;

        return Section::make('Forme')
            ->description('Le dessin du marqueur, sa taille et sa place par rapport à la position du point (la croix rouge de l’aperçu).')
            ->columns(2)
            ->schema([
                Select::make('marker_style.shape')
                    ->label('Forme')
                    ->options(MarkerShapes::LABELS)
                    ->default('pin')
                    ->required()
                    ->live(),
                Select::make('marker_style.anchor')
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
                    ->helperText('Le point de la forme posé sur la position. Vide : la pointe d’une épingle, le data-anchor d’un SVG, sinon le centre.'),
                Textarea::make('marker_style.svg')
                    ->label('SVG personnalisé')
                    ->visible(fn (Get $get): bool => $get('marker_style.shape') === 'svg')
                    // Gardé quand on essaie une autre forme : on peut y revenir sans le perdre.
                    ->dehydratedWhenHidden()
                    ->live(debounce: 600)
                    ->helperText('Avec une viewBox. currentColor prend la couleur du point. La zone qui reçoit l’icône, l’image ou le texte est un circle, une ellipse ou un rect marqué data-slot (il n’est pas dessiné) ; sans elle, la forme reste seule. data-anchor="bottom" sur la balise svg pose sa base sur la position (centre par défaut). Scripts, contenus embarqués et liens externes sont retirés ; un SVG illisible laisse place à l’épingle.')
                    ->rows(6)
                    ->columnSpanFull(),
                Slider::make('marker_style.size')
                    ->label('Taille')
                    ->range(MarkerShapes::MIN_PERCENT, MarkerShapes::MAX_PERCENT)
                    ->step(5)
                    ->default(100)
                    ->tooltips($percentTooltip)
                    ->formatStateUsing(fn (mixed $state): float => MarkerShapes::percent($state))
                    ->live()
                    ->helperText('En % de la taille standard : '.MarkerShapes::STANDARD_SIZE.' px sur le plus grand côté, celle de l’épingle. L’autre côté suit les proportions de la forme.')
                    ->columnSpanFull(),
                Slider::make('marker_style.rotation')
                    ->label('Rotation')
                    ->range(-180, 180)
                    ->step(5)
                    ->default(0)
                    ->tooltips(RawJs::make('`${Math.round($value)}°`'))
                    ->formatStateUsing($number)
                    ->live()
                    ->helperText('En degrés, autour de l’ancrage.')
                    ->columnSpanFull(),
                Slider::make('marker_style.offset.x')
                    ->label('Décalage horizontal')
                    ->range(-MarkerShapes::MAX_OFFSET, MarkerShapes::MAX_OFFSET)
                    ->step(5)
                    ->default(0)
                    ->tooltips($percentTooltip)
                    ->formatStateUsing($number)
                    ->live()
                    ->helperText('En % de la largeur du marqueur, vers la droite : il suit la taille.'),
                Slider::make('marker_style.offset.y')
                    ->label('Décalage vertical')
                    ->range(-MarkerShapes::MAX_OFFSET, MarkerShapes::MAX_OFFSET)
                    ->step(5)
                    ->default(0)
                    ->tooltips($percentTooltip)
                    ->formatStateUsing($number)
                    ->live()
                    ->helperText('En % de sa hauteur, vers le bas.'),
            ]);
    }

    /**
     * Ce que montre la zone de contenu de la forme. Un encadré dit d'abord si la forme en a une (donc si une icône, une
     * image ou un texte y sont possibles) ; chaque choix explique d'où vient ce qu'il montre ; seul le champ qui sert
     * est affiché : l'icône (montrée aussi à défaut d'image), le texte, l'image par défaut.
     */
    protected static function contentSection(): Section
    {
        $hasSlot = fn (Get $get): bool => MarkerShapes::resolve((array) ($get('marker_style') ?? []))['slot'] !== null;
        $content = fn (Get $get): ?string => $get('marker_style.content.type');

        return Section::make('Contenu de la zone')
            ->description('Ce que le marqueur montre à l’intérieur de sa forme : rien, une icône, une image ou un texte.')
            ->schema([
                Callout::make(fn (Get $get): string => $hasSlot($get)
                    ? 'Cette forme a une zone de contenu : une icône, une image ou un texte y sont possibles.'
                    : 'Cette forme n’a pas de zone de contenu : elle ne montre qu’elle-même.')
                    ->description(fn (Get $get): string => $hasSlot($get)
                        ? 'La zone est en pointillés dans l’aperçu agrandi.'
                        : 'Pour y mettre une icône ou une image, marquez un circle, une ellipse ou un rect du SVG avec data-slot.')
                    ->status(fn (Get $get): string => $hasSlot($get) ? 'info' : 'warning'),
                Radio::make('marker_style.content.type')
                    ->label('La zone montre')
                    ->options([
                        'none' => 'Rien : la forme seule',
                        'icon' => 'Une icône',
                        'image' => 'Une image (mini-vignette)',
                        'text' => 'Un texte',
                    ])
                    ->descriptions([
                        'none' => 'Le point n’est que sa forme et sa couleur.',
                        'icon' => 'L’icône choisie ci-dessous. Un parcours peut la remplacer pour un point : dans le voyage, une période peut prendre la sienne.',
                        'image' => 'L’image que le parcours donne au point — dans le voyage, l’image de une de l’étape, à défaut sa première photo —, sinon l’image par défaut ci-dessous. Sans aucune image, l’icône.',
                        'text' => 'Un texte court, le même pour tous les points du type : un numéro, deux lettres.',
                    ])
                    ->default('icon')
                    ->required()
                    ->disableOptionWhen(fn (string $value, Get $get): bool => $value !== 'none' && ! $hasSlot($get))
                    ->live(),
                IconPicker::make('icon')
                    ->label(fn (Get $get): string => $content($get) === 'image' ? 'Icône, à défaut d’image' : 'Icône')
                    ->visible(fn (Get $get): bool => in_array($content($get), ['icon', 'image'], true))
                    ->dehydratedWhenHidden()
                    ->live(),
                TextInput::make('marker_style.content.value')
                    ->label('Texte')
                    ->visible(fn (Get $get): bool => $content($get) === 'text')
                    ->dehydratedWhenHidden()
                    ->maxLength(4)
                    ->live(onBlur: true),
                SpatieMediaLibraryFileUpload::make('default_marker_image')
                    ->visible(fn (Get $get): bool => $content($get) === 'image')
                    ->disabled(fn (): bool => ! MapPermissions::allows(static::class, 'attach-media'))
                    ->label('Image par défaut')
                    ->helperText('Montrée quand ni le point ni le parcours n’en donnent une.')
                    ->collection(config('filament-map.media_collections.default_marker_image', 'default_marker_image'))
                    ->image(),
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
                static::replicateAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Dupliquer un type : tout son rendu (forme, contenu, taille, position, style, options) et son image par défaut. On
     * ne demande que le nom et la clé de la copie, proposés d'après l'original ; on arrive ensuite sur sa fiche. Les
     * points de l'original restent à lui. Avec filament-permission-manager, il faut le droit de créer un type.
     */
    public static function replicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Dupliquer')
            ->modalHeading(fn (GeoPointType $record): string => 'Dupliquer « '.$record->name.' »')
            ->modalDescription('La copie reprend tout le rendu et l’image par défaut. Seuls son nom et sa clé changent.')
            ->modalSubmitActionLabel('Dupliquer')
            ->excludeAttributes(['points_count'])
            ->mutateRecordDataUsing(fn (array $data): array => [
                'name' => ($data['name'] ?? 'Type').' (copie)',
                'key' => static::uniqueKey(($data['key'] ?? 'type').'-copie'),
            ])
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                TextInput::make('key')
                    ->label('Clé')
                    ->required()
                    ->maxLength(255)
                    ->unique(GeoPointType::class, 'key'),
            ])
            // replicate() reprend aussi les relations chargées : les médias de l'original ne sont pas ceux de la copie.
            ->beforeReplicaSaved(fn (GeoPointType $replica) => $replica->setRelations([]))
            ->after(function (GeoPointType $record, GeoPointType $replica): void {
                $collection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');
                $record->getFirstMedia($collection)?->copy($replica, $collection);
            })
            ->successNotificationTitle('Type dupliqué')
            ->successRedirectUrl(fn (GeoPointType $replica): string => static::getUrl('edit', ['record' => $replica]));
    }

    /** Une clé libre : celle-ci, sinon suivie de -2, -3… */
    protected static function uniqueKey(string $key): string
    {
        $key = Str::slug($key);
        $candidate = $key;

        for ($i = 2; GeoPointType::query()->where('key', $candidate)->exists(); $i++) {
            $candidate = "{$key}-{$i}";
        }

        return $candidate;
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
