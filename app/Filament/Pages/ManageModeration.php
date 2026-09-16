<?php

namespace App\Filament\Pages;

use App\Settings\ModerationSettings;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageModeration extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = ModerationSettings::class;

    protected static ?string $navigationGroup = 'Settings';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Toggle::make('location_moderation_enabled')
                    ->label('Включить модерацию локаций')
                    ->helperText('Если включено, новые локации будут требовать одобрения администратора.')
                    ->dehydrateStateUsing(fn ($state): bool => boolval($state)),

                Toggle::make('photo_moderation_enabled')
                    ->label('Включить модерацию фотографий')
                    ->helperText('Если включено, фотографии появляются в каталоге только после одобрения модератором (рекомендуется).')
                    ->dehydrateStateUsing(fn ($state): bool => boolval($state)),
            ]);
    }
}
