<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\MapPositionInput;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages\CreateGeoPoint;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages\EditGeoPoint;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages\ListGeoPoints;
use CharlesStOlive\FilamentMap\Models\GeoPoint;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use CharlesStOlive\FilamentMap\Support\MapPermissions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Support\Str;

class GeoPointResource extends Resource implements HasKnowledgeBase
{
    use BelongsToConfiguredMapCluster;

    /**
     * Action propre, déclarée au format de charlesstolive/filament-permission-manager, sans en dépendre (voir
     * Support\MapPermissions) : joindre ou changer l'image de marqueur d'un point.
     *
     * @var array<int, string>
     */
    public static array $specificPermissions = ['attach-media'];

    /** @var array<string, string> Son libellé dans l'écran des rôles. */
    protected static array $permissionLabels = ['attach-media' => 'Joindre ou changer l’image de marqueur'];

    protected static ?string $model = GeoPoint::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Points géographiques';

    public static function getDocumentation(): array|string
    {
        return ['map.points', 'map.types-de-points', 'map.apparence'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Point')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Select::make('geo_point_type_id')
                        ->label('Type')
                        ->relationship('type', 'name')
                        ->searchable()
                        ->preload(),
                    Toggle::make('is_active')->default(true),
                    Textarea::make('description')->columnSpanFull(),
                ]),
            Section::make('Coordonnées')
                ->columns(2)
                ->schema([
                    MapPositionInput::make()->label('Coordonnées')->required(),
                ]),
            Section::make('Apparence')
                ->description('Les comportements sont désormais définis centralement dans une orchestration.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('marker_image')
                        ->disabled(fn (): bool => ! MapPermissions::allows(static::class, 'attach-media'))
                        ->label('Image propre à ce point')
                        ->collection(config('filament-map.media_collections.marker_image', 'marker_image'))
                        ->image()
                        ->columnSpanFull(),
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
                TextColumn::make('type.name')->label('Type')->badge()->sortable(),
                TextColumn::make('latitude')->sortable(),
                TextColumn::make('longitude')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeoPoints::route('/'),
            'create' => CreateGeoPoint::route('/create'),
            'edit' => EditGeoPoint::route('/{record}/edit'),
        ];
    }
}
