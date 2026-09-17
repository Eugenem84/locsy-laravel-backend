<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Пользователи проекта: роли (фотограф / ищу места) и выдача прав
 * администратора (`is_admin`) — без этого войти в админку нельзя,
 * см. User::canAccessPanel().
 *
 * Пароль здесь можно сменить вручную (например, когда письмо для сброса
 * не доходит); штатный путь для пользователя — «Забыли пароль» в SPA.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Администрирование';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->label('Новый пароль')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    // Пустое поле = «не менять пароль»: гидрируем только заполненное,
                    // иначе модель затрёт текущий хеш.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Оставьте пустым, чтобы не менять. Пользователь может сменить пароль сам — «Забыли пароль» в SPA.'),
                Forms\Components\Select::make('city_id')
                    ->label('Город')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Toggle::make('is_photographer')
                    ->label('Фотограф')
                    ->helperText('Показывается фотографам в профиле и на странице /photographer/{id}.'),
                Forms\Components\Toggle::make('is_admin')
                    ->label('Администратор')
                    ->helperText('Доступ в админку модерации (/admin).'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Аватар')
                    ->circular()
                    // Модель отдаёт путь вида /storage/avatars/…, а колонка по
                    // умолчанию считает значение путём на диске и склеила бы
                    // «/storage/storage/…». Приводим к абсолютному URL.
                    ->getStateUsing(fn (User $record): ?string => $record->avatar ? url($record->avatar) : null),
                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Город')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_photographer')
                    ->label('Фотограф')
                    ->boolean(),
                Tables\Columns\ToggleColumn::make('is_admin')
                    ->label('Админ'),
                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Локации')
                    ->counts('locations')
                    ->badge(),
                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Фото')
                    ->counts('photos')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Регистрация')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_admin')
                    ->label('Администраторы')
                    ->options(['1' => 'Только админы', '0' => 'Без админов']),
                SelectFilter::make('is_photographer')
                    ->label('Фотографы')
                    ->options(['1' => 'Только фотографы', '0' => 'Ищущие места']),
                SelectFilter::make('city_id')
                    ->label('Город')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
