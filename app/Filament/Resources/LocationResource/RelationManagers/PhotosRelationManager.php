<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Show preview only on the edit page
                Forms\Components\View::make('filament.components.image-preview')
                    ->visible(fn (?Model $record) => $record instanceof Photo)
                    ->viewData(fn (?Photo $record) => [
                        'url' => $record?->full_url,
                    ]),

                Forms\Components\FileUpload::make('path')
                    ->required()
                    ->image()
                    ->disk('public')
                    ->directory('photos')
                    ->label('Photo'),
                Forms\Components\Select::make('status')
                    ->label('Статус модерации')
                    ->options(PhotoStatus::class)
                    ->default(PhotoStatus::Approved)
                    ->required(),
                Forms\Components\Toggle::make('is_main')
                    ->label('Обложка локации'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('full_url')
                    ->label('Фото')
                    ->height(70),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус'),
                Tables\Columns\IconColumn::make('is_main')
                    ->boolean()
                    ->label('Обложка'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Автор')
                    ->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Photo $record) => $record->status !== PhotoStatus::Approved)
                    ->action(fn (Photo $record) => $record->update([
                        'status' => PhotoStatus::Approved,
                        'moderation_note' => null,
                    ])),
                Tables\Actions\Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('moderation_note')
                            ->label('Причина отказа (увидит автор)')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (Photo $record) => $record->status !== PhotoStatus::Rejected)
                    ->action(fn (Photo $record, array $data) => $record->update([
                        'status' => PhotoStatus::Rejected,
                        'moderation_note' => $data['moderation_note'],
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
