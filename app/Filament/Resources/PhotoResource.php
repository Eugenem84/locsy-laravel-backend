<?php

namespace App\Filament\Resources;

use App\Enums\PhotoStatus;
use App\Filament\Resources\PhotoResource\Pages;
use App\Models\Photo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Очередь модерации фотографий.
 * Публично (в API) показываются только фото со статусом «Одобрено».
 */
class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Модерация';

    protected static ?string $navigationLabel = 'Фотографии';

    protected static ?string $modelLabel = 'фотография';

    protected static ?string $pluralModelLabel = 'Фотографии';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = Photo::query()->where('status', PhotoStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Фотографии на модерации';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('Статус модерации')
                    ->options(PhotoStatus::class)
                    ->required(),
                Forms\Components\TextInput::make('moderation_note')
                    ->label('Комментарий модератора (виден автору при отказе)')
                    ->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Новая фотография может прийти, пока модератор держит список открытым.
            ->poll('30s')
            ->columns([
                ImageColumn::make('full_url')
                    ->label('Фото')
                    ->height(90)
                    ->extraImgAttributes(['style' => 'border-radius: 8px; object-fit: cover;']),
                TextColumn::make('location.name')
                    ->label('Локация')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Автор')
                    ->searchable()
                    ->placeholder('—'),
                BadgeColumn::make('status')
                    ->label('Статус')
                    ->sortable(),
                TextColumn::make('moderation_note')
                    ->label('Причина отказа')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Загружено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(PhotoStatus::class),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Photo $record) => $record->update([
                        'status' => PhotoStatus::Approved,
                        'moderation_note' => null,
                    ]))
                    ->visible(fn (Photo $record) => $record->status !== PhotoStatus::Approved),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('moderation_note')
                            ->label('Причина отказа (увидит автор)')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(fn (Photo $record, array $data) => $record->update([
                        'status' => PhotoStatus::Rejected,
                        'moderation_note' => $data['moderation_note'],
                    ]))
                    ->visible(fn (Photo $record) => $record->status !== PhotoStatus::Rejected),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $records->each->update([
                            'status' => PhotoStatus::Approved,
                            'moderation_note' => null,
                        ])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotos::route('/'),
            'edit' => Pages\EditPhoto::route('/{record}/edit'),
        ];
    }
}
