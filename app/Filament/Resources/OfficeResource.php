<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfficeResource\Pages;
use App\Filament\Resources\OfficeResource\RelationManagers;
use App\Models\Office;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use GuzzleHttp\Client;
use Humaidem\FilamentMapPicker\Fields\OSMMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Master';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Office Information')  // Section for office details
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Office Name')
                            ->required()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Location Details')  // Section for location details
                    ->schema([
                        Forms\Components\Group::make()  // Group address-related fields
                            ->schema([
                                Forms\Components\TextInput::make('address')
                                    ->label('Search Address')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if (empty($state)) {
                                            $set('latitude', '');
                                            $set('longitude', '');
                                            $set('location', ['lat' => 0, 'lng' => 0]);
                                            return;
                                        }

                                        $coordinates = self::getCoordinatesFromAddress($state);

                                        if ($coordinates) {
                                            $set('latitude', $coordinates['lat']);
                                            $set('longitude', $coordinates['lng']);
                                            $set('location', ['lat' => $coordinates['lat'], 'lng' => $coordinates['lng']]);
                                        } else {
                                            $set('latitude', '');
                                            $set('longitude', '');
                                            $set('location', ['lat' => 0, 'lng' => 0]);
                                        }
                                    }),

                                OSMMap::make('location')
                                    ->label('Location')
                                    ->showMarker()
                                    ->draggable()
                                    ->extraControl([
                                        'zoomDelta' => 1,
                                        'zoomSnap' => 0.25,
                                        'wheelPxPerZoomLevel' => 60
                                    ])
                                    ->tilesUrl('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')
                                    ->afterStateHydrated(function ($get, $set, $record) {
                                        $latitude = $record ? $record->latitude : $get('latitude');
                                        $longitude = $record ? $record->longitude : $get('longitude');

                                        if ($latitude && $longitude) {
                                            $set('location', ['lat' => $latitude, 'lng' => $longitude]);
                                        } else {
                                            $set('location', ['lat' => 0, 'lng' => 0]);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $set('latitude', $state['lat']);
                                        $set('longitude', $state['lng']);
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)  // Two-column grid for latitude and longitude
                            ->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $longitude = $get('longitude');
                                        if ($state && $longitude) {
                                            $set('location', ['lat' => $state, 'lng' => $longitude]);
                                        }
                                    })
                                    ->readonly(false),

                                Forms\Components\TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $latitude = $get('latitude');
                                        if ($latitude && $state) {
                                            $set('location', ['lat' => $latitude, 'lng' => $state]);
                                        }
                                    })
                                    ->readonly(false),
                            ]),

                        Forms\Components\TextInput::make('radius')
                            ->label('Radius (in meters)')
                            ->required()
                            ->numeric()
                            ->default(10),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->sortable(),
                Tables\Columns\TextColumn::make('longitude')
                    ->sortable(),
                Tables\Columns\TextColumn::make('radius')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffices::route('/'),
            'create' => Pages\CreateOffice::route('/create'),
            'edit' => Pages\EditOffice::route('/{record}/edit'),
        ];
    }

    private static function getCoordinatesFromAddress(string $address): ?array
    {
        try {
            $client = new Client();
            $apiKey = env('GOOGLE_MAPS_API_KEY');  // Pastikan Anda menambahkan API Key di file .env
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $address,
                    'key' => $apiKey,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'OK') {
                $location = $data['results'][0]['geometry']['location'];
                return [
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
