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
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Локации фотографов: создание, редактирование и модерация
 * (статусы pending / approved / rejected, см. LocationStatus).
 *
 * В публичном API видны только локации со статусом «approved», поэтому
 * очередь «На модерации» — основная работа модератора в этом ресурсе.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Модерация';

    protected static ?string $navigationLabel = 'Локации';

    protected static ?string $modelLabel = 'локация';

    protected static ?string $pluralModelLabel = 'Локации';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $pending = Location::query()->where('status', LocationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Локации на модерации';
    }

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
                    ->label('Город')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Автор')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Статус модерации')
                    ->options(LocationStatus::class)
                    ->default(LocationStatus::Pending)
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
            ->defaultSort('created_at', 'desc')
            // Очередь модерации обновляем автоматически: автор может прислать
            // новую локацию, пока модератор смотрит список.
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Город')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Автор')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Категории')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Фото')
                    ->counts('photos')
                    ->badge(),
                BadgeColumn::make('status')
                    ->label('Статус')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(LocationStatus::class)
                    // Значение по умолчанию — строка (->value), а не сам enum:
                    // SelectFilter строит подпись индикатора через Collection::get(),
                    // где объект-ключ даёт TypeError.
                    ->default(LocationStatus::Pending->value),
                SelectFilter::make('city_id')
                    ->label('Город')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Location $record) => $record->update(['status' => LocationStatus::Approved]))
                    ->visible(fn (Location $record) => $record->status !== LocationStatus::Approved),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Location $record) => $record->update(['status' => LocationStatus::Rejected]))
                    ->visible(fn (Location $record) => $record->status !== LocationStatus::Rejected),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (EloquentCollection $records) => $records->each->update([
                            'status' => LocationStatus::Approved,
                        ])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(25);
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
