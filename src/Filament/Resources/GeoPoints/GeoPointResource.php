<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
use CharlesStOlive\FilamentMap\Filament\Forms\Components\CoordinatePicker;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GeoPointResource extends Resource
{
    use BelongsToConfiguredMapCluster;
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['pick-coordinates', 'attach-media'];

    protected static ?string $model = GeoPoint::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Points géographiques';

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
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
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
                    TextInput::make('latitude')->numeric()->step('0.0000001')->required(),
                    TextInput::make('longitude')->numeric()->step('0.0000001')->required(),
                    CoordinatePicker::make('coordinate_picker')
                        ->dehydrated(false)
                        ->latitudeField('latitude')
                        ->longitudeField('longitude')
                        ->columnSpanFull(),
                ]),
            Section::make('Cartes')
                ->schema([
                    Select::make('maps')
                        ->relationship('maps', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Apparence')
                ->description('Les comportements sont désormais définis centralement dans une orchestration.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('marker_image')
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
                TextColumn::make('maps_count')->counts('maps')->label('Cartes'),
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
