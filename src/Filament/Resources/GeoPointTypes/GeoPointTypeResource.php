<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes;

use CharlesStOlive\FilamentMap\Filament\Concerns\BelongsToConfiguredMapCluster;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapResourceAuthorization;
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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GeoPointTypeResource extends Resource
{
    use BelongsToConfiguredMapCluster;
    use HasMapResourceAuthorization;

    public static array $specificPermissions = ['attach-media'];

    protected static ?string $model = GeoPointType::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Types de points';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Type de point')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')->required()->unique(ignoreRecord: true),
                    TextInput::make('icon')->helperText('Ex: heroicon-o-map-pin, lucide-camera ou cle custom.'),
                    ColorPicker::make('color'),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_active')->default(true),
                    Textarea::make('description')->columnSpanFull(),
                ]),
            Section::make('Rendu par defaut')
                ->description('Le type pilote la forme et le contenu visuel des points. Un SVG personnalise sera rendu comme une forme, pas comme du contenu metier.')
                ->columns(2)
                ->statePath('marker_style')
                ->schema([
                    Select::make('shape')
                        ->label('Forme')
                        ->options([
                            'pin' => 'Goutte / epingle',
                            'circle' => 'Cercle',
                            'star' => 'Etoile',
                            'svg' => 'SVG personnalise',
                        ])
                        ->default('pin')
                        ->required(),
                    Select::make('content.type')
                        ->label('Contenu du point')
                        ->options([
                            'none' => 'Aucun',
                            'icon' => 'Icone',
                            'image' => 'Image',
                            'text' => 'Texte',
                        ])
                        ->default('icon')
                        ->required(),
                    Textarea::make('svg')
                        ->label('SVG personnalise')
                        ->helperText('Le SVG devra etre nettoye avant son rendu dans le navigateur.')
                        ->rows(6)
                        ->columnSpanFull(),
                    TextInput::make('content.value')
                        ->label('Icone ou texte')
                        ->helperText('Pour une image, la collection Media Library du type sera utilisee.'),
                    TextInput::make('size.width')
                        ->label('Largeur')
                        ->numeric(),
                    TextInput::make('size.height')
                        ->label('Hauteur')
                        ->numeric(),
                    KeyValue::make('css')
                        ->label('Variables de style')
                        ->columnSpanFull(),
                ]),
            Section::make('Options avancees')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('default_marker_image')
                        ->label('Image du point')
                        ->collection(config('filament-map.media_collections.default_marker_image', 'default_marker_image'))
                        ->image()
                        ->columnSpanFull(),
                    KeyValue::make('options'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('icon')->toggleable(),
                ColorColumn::make('color'),
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
