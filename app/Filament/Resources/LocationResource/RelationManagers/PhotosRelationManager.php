<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

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
                Forms\Components\Toggle::make('is_main')
                    ->required()
                    ->label('Main Photo'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('full_url')
                    ->label('Image'),
                Tables\Columns\IconColumn::make('is_main')
                    ->boolean()
                    ->label('Is Main'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
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
