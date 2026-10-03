<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapLayerPreview;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\CreateMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\EditMapLayer;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages\ListMapLayers;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Services\MapLayerChecker;
use CharlesStOlive\FilamentMap\Support\MapKeys;
use CharlesStOlive\FilamentMap\Support\MapLayerCheck;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use CharlesStOlive\FilamentMap\Support\MapPermissions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MapLayerResource extends Resource implements HasKnowledgeBase
{
    use BelongsToConfiguredMapCluster;

    /**
     * Action propre, déclarée au format de charlesstolive/filament-permission-manager, sans en dépendre (voir
     * Support\MapPermissions) : voir l'aperçu d'une couche dans son formulaire.
     *
     * @var array<int, string>
     */
    public static array $specificPermissions = ['preview'];

    /** @var array<string, string> Son libellé dans l'écran des rôles. */
    protected static array $permissionLabels = ['preview' => 'Voir l’aperçu d’une couche sur la carte'];

    protected static ?string $model = MapLayer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Couches cartographiques';

    public static function getDocumentation(): array|string
    {
        return ['map.couches', 'map.apparence'];
    }

    /** Les couches de données, dont la source peut être une URL, du JSON collé ou un fichier. Un fond n'a qu'une URL. */
    protected const DATA_TYPES = ['geojson', 'points', 'svg_overlay', 'custom'];

    protected const SHORT_TYPE_LABELS = [
        'style' => 'Fond vectoriel',
        'tile' => 'Fond en tuiles',
        'geojson' => 'GeoJSON',
        'points' => 'Points',
        'svg_overlay' => 'SVG',
        'custom' => 'Personnalisée',
    ];

    public static function form(Schema $schema): Schema
    {
        $isData = fn (Get $get): bool => in_array($get('type'), self::DATA_TYPES, true);
        $isBaseMap = fn (Get $get): bool => in_array($get('type'), ['style', 'tile'], true);

        return $schema->components([
            Callout::make(fn (MapLayer $record): string => 'État : '.MapLayerCheck::label($record->check_status))
                ->status(fn (MapLayer $record): string => match ($record->check_status) {
                    MapLayer::CHECK_OK => 'success',
                    MapLayer::CHECK_WARNING => 'warning',
                    MapLayer::CHECK_ERROR => 'danger',
                    default => 'info',
                })
                ->description(fn (MapLayer $record): string => $record->check_status === null
                    ? 'Pas encore vérifiée : elle le sera au prochain enregistrement, ou avec « Vérifier ».'
                    : $record->check_message.' (vérifiée le '.$record->checked_at?->format('d/m/Y à H:i').')')
                ->actions([static::checkAction()->label('Vérifier à nouveau')])
                ->visible(fn (?MapLayer $record): bool => $record?->exists ?? false)
                ->columnSpanFull(),
            Section::make('Couche')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')
                        ->label('Clé')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Select::make('type')
                        ->label('Type')
                        ->options(MapLayer::types())
                        ->default('geojson')
                        ->required()
                        ->native(false)
                        ->helperText(fn (Get $get): ?string => static::typeHelp($get('type')))
                        ->live(),
                    Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                    Select::make('source_type')
                        ->label('Type de source')
                        ->options([
                            'url' => 'URL',
                            'json' => 'JSON brut',
                            'file' => 'Fichier uploadé',
                        ])
                        ->default('url')
                        ->required()
                        ->visible($isData)
                        ->live(),
                    TextInput::make('source_url')
                        ->label(fn (Get $get): string => match ($get('type')) {
                            'style' => 'URL du style (style.json)',
                            'tile' => 'URL des tuiles',
                            default => 'URL source',
                        })
                        ->required($isBaseMap)
                        ->helperText(fn (Get $get): string => match ($get('type')) {
                            'style' => 'Par exemple https://api.maptiler.com/maps/<identifiant>/style.json?key={key:maptiler}. Une clé collée telle quelle est remplacée par {key:…} si elle est configurée.',
                            'tile' => 'Par exemple https://tile.openstreetmap.org/{z}/{x}/{y}.png. {z}, {x} et {y} sont remplacés par le zoom et la position.',
                            default => 'URL distante ou URL publique Laravel, par exemple /storage/maps/asie-sudest.geojson.',
                        })
                        ->visible(fn (Get $get): bool => $isBaseMap($get) || $get('source_type') === 'url')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => static::normalizeSourceUrl($state, $get, $set))
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
                        ->visible(fn(Get $get): bool => $isData($get) && $get('source_type') === 'file')
                        ->live()
                        ->columnSpanFull(),
                    static::jsonTextarea('source_json')
                        ->label('Source JSON')
                        ->helperText('GeoJSON collé directement. Pratique pour tester, moins adapté aux gros fichiers.')
                        ->rows(12)
                        ->visible(fn(Get $get): bool => $isData($get) && $get('source_type') === 'json')
                        ->columnSpanFull(),
                    Select::make('preview_scene_id')
                        ->label('Scène d’exemple')
                        ->relationship('previewScene', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('Utilisée uniquement pour prévisualiser la couche ci-dessous.'),
                ]),
            Section::make(fn (Get $get): string => in_array($get('type'), ['geojson', 'points'], true) ? 'Style et options' : 'Options')
                ->columns(fn (Get $get): int => in_array($get('type'), ['geojson', 'points'], true) ? 3 : 1)
                ->schema([
                    static::jsonTextarea('style')
                        ->label('Style JSON')
                        ->helperText('Style MapLibre appliqué par défaut à toutes les entités GeoJSON de la couche.')
                        ->visible(fn (Get $get): bool => in_array($get('type'), ['geojson', 'points'], true)),
                    static::jsonTextarea('style_rules')
                        ->label('Règles JSON')
                        ->helperText('Overrides de style selon les properties GeoJSON, par exemple ADM0_A3 = KHM.')
                        ->visible(fn (Get $get): bool => in_array($get('type'), ['geojson', 'points'], true)),
                    static::jsonTextarea('options')
                        ->label('Options JSON')
                        ->helperText(fn (Get $get): string => match ($get('type')) {
                            'style' => 'Rarement utile : le fond vient tout entier du style.json. Les autres clés sont ignorées à l’affichage.',
                            'tile' => 'Par exemple {"attribution": "© OpenStreetMap contributors"}, le crédit affiché en bas de carte.',
                            'svg_overlay' => 'L’emprise de l’image, obligatoire : {"bounds": {"southWest": {"lat": …, "lng": …}, "northEast": {"lat": …, "lng": …}}}.',
                            default => 'Options techniques du moteur de rendu (MapLibre). À laisser vide pour une simple coloration GeoJSON.',
                        }),
                ]),
            Section::make('Aperçu')
                ->schema([
                    MapLayerPreview::make('layer_preview')
                        ->visible(fn (): bool => MapPermissions::allows(static::class, 'preview'))
                        ->label('Preview du layer')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** Un champ JSON : affiché indenté, enregistré décodé, refusé s'il ne se lit pas (au lieu de partir vide en silence). */
    protected static function jsonTextarea(string $name): Textarea
    {
        return Textarea::make($name)
            ->rows(10)
            ->formatStateUsing(fn($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $state)
            ->dehydrateStateUsing(fn($state): ?array => is_array($state) ? $state : (filled($state) ? json_decode($state, true) : null))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && filled($value) && ! is_array(json_decode($value, true))) {
                    $fail('JSON illisible : '.json_last_error_msg().'.');
                }
            })
            ->live(onBlur: true);
    }

    protected static function typeHelp(?string $type): ?string
    {
        return match ($type) {
            'style' => 'Un fond complet décrit par un fichier style.json (MapTiler, TileCat…) : routes, relief, libellés. Il remplace le fond de la carte.',
            'tile' => 'Un fond fait d’images carrées, à une adresse qui contient {z}, {x} et {y} (OpenStreetMap…).',
            'geojson' => 'Des lignes ou des zones posées sur le fond, lues dans un GeoJSON.',
            'points' => 'Des points posés sur le fond, lus dans un GeoJSON.',
            'svg_overlay' => 'Une image SVG posée sur une emprise géographique, précisée dans les options.',
            'custom' => 'Un rendu fourni par le projet (MapLibreMapInstance.registerLayerRenderer).',
            default => null,
        };
    }

    /**
     * Une URL collée telle quelle : la clé d'un fournisseur configuré devient « {key:…} » (elle reste dans le .env), et une
     * adresse de style.json ou de tuiles choisit le type de fond qui lui correspond.
     */
    protected static function normalizeSourceUrl(?string $url, Get $get, Set $set): void
    {
        if (! filled($url)) {
            return;
        }

        $normalized = trim($url);

        foreach (MapKeys::all() as $name => $value) {
            $normalized = str_replace($value, '{key:'.$name.'}', $normalized);
        }

        if ($normalized !== $url) {
            $set('source_url', $normalized);
        }

        $path = Str::before($normalized, '?');

        if (str_ends_with($path, 'style.json') && in_array($get('type'), ['tile', 'geojson', null], true)) {
            $set('type', 'style');
        } elseif (str_contains($path, '{z}') && in_array($get('type'), ['style', 'geojson', null], true)) {
            $set('type', 'tile');
        }
    }

    /** Vérifie la version enregistrée de la couche et en notifie le résultat (voir MapLayerChecker). */
    public static function checkAction(): Action
    {
        return Action::make('check')
            ->label('Vérifier')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->visible(fn (MapLayer $record): bool => static::canEdit($record))
            ->action(function (MapLayer $record): void {
                static::checkNotification(app(MapLayerChecker::class)->checkAndStore($record), $record->name)->send();
            });
    }

    public static function checkNotification(MapLayerCheck $check, string $name, ?string $title = null): Notification
    {
        return Notification::make()
            ->title($title ?? ('« '.$name.' » : '.mb_strtolower(MapLayerCheck::label($check->status))))
            ->body($check->message)
            ->status(match ($check->status) {
                MapLayer::CHECK_OK => 'success',
                MapLayer::CHECK_WARNING => 'warning',
                default => 'danger',
            })
            ->persistent($check->status !== MapLayer::CHECK_OK);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('previewScene.name')->label('Scène d’exemple')->toggleable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (MapLayer $record): string => self::SHORT_TYPE_LABELS[$record->renderType()] ?? (string) $record->type)
                    ->sortable(),
                TextColumn::make('check_status')
                    ->label('État')
                    ->badge()
                    ->default('unchecked')
                    ->formatStateUsing(fn (MapLayer $record): string => MapLayerCheck::label($record->check_status))
                    ->color(fn (MapLayer $record): string => MapLayerCheck::color($record->check_status))
                    ->icon(fn (MapLayer $record): string => MapLayerCheck::icon($record->check_status))
                    ->tooltip(fn (MapLayer $record): ?string => $record->check_message
                        ? $record->check_message.' — '.$record->checked_at?->diffForHumans()
                        : null)
                    ->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                static::checkAction()->iconButton()->tooltip('Vérifier que la couche s’affiche'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('check')
                        ->label('Vérifier')
                        ->icon('heroicon-o-signal')
                        ->action(function (Collection $records): void {
                            $checker = app(MapLayerChecker::class);
                            $checks = $records->filter(fn (MapLayer $layer): bool => static::canEdit($layer))
                                ->map(fn (MapLayer $layer): MapLayerCheck => $checker->checkAndStore($layer));
                            $errors = $checks->where('status', MapLayer::CHECK_ERROR)->count();
                            $warnings = $checks->where('status', MapLayer::CHECK_WARNING)->count();

                            Notification::make()
                                ->title($checks->count().' couche(s) vérifiée(s)')
                                ->body($errors + $warnings === 0
                                    ? 'Toutes fonctionnent.'
                                    : "{$errors} en erreur, {$warnings} à surveiller : la colonne « État » en donne la raison.")
                                ->status($errors > 0 ? 'danger' : ($warnings > 0 ? 'warning' : 'success'))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
