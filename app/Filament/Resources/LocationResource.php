<?php

namespace App\Filament\Resources;

use App\Enums\LocationStatus;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers;
use App\Forms\Components\MapPicker;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('city_id')
                    ->relationship('city', 'name')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(LocationStatus::class)
                    ->required(),

                // Поле для выбора категорий
                Forms\Components\Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),

                // Our new map component
                MapPicker::make('location')
                    ->label('Location on map')
                    ->latitude('latitude') // Connects to 'latitude' field
                    ->longitude('longitude') // Connects to 'longitude' field
                    ->columnSpanFull(),

                // Hidden latitude and longitude fields
                Forms\Components\TextInput::make('latitude')
                    ->required()
                    ->numeric()
                    ->reactive() // Important for live updates
                    ->label('Latitude'),
                Forms\Components\TextInput::make('longitude')
                    ->required()
                    ->numeric()
                    ->reactive() // Important for live updates
                    ->label('Longitude'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('description')->limit(50),
                Tables\Columns\TextColumn::make('city.name'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('categories.name')
                    ->badge()
                    ->color('primary'),
                BadgeColumn::make('status')
                    ->label('Статус')
                    ->colors([
                        'warning' => LocationStatus::Pending->value,
                        'success' => LocationStatus::Approved->value,
                        'danger' => LocationStatus::Rejected->value,
                    ])
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Location $record) => $record->update(['status' => LocationStatus::Approved]))
                    ->visible(fn (Location $record) => $record->status === LocationStatus::Pending), // Показываем только для ожидающих

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(fn (Location $record) => $record->update(['status' => LocationStatus::Rejected]))
                    ->visible(fn (Location $record) => $record->status === LocationStatus::Pending), // Показываем только для ожидающих
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
            RelationManagers\PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
