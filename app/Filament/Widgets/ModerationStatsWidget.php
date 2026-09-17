<?php

namespace App\Filament\Widgets;

use App\Enums\LocationStatus;
use App\Enums\PhotoStatus;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\PhotoResource;
use App\Filament\Resources\UserResource;
use App\Models\Location;
use App\Models\Photo;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Дашборд модератора: очередь модерации и состояние каталога «одним взглядом».
 * Поднят выше штатных виджетов Filament (AccountWidget/FilamentInfoWidget).
 */
class ModerationStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $pendingPhotos = Photo::query()->where('status', PhotoStatus::Pending)->count();
        $pendingLocations = Location::query()->where('status', LocationStatus::Pending)->count();

        return [
            Stat::make('Фото на модерации', (string) $pendingPhotos)
                ->description($pendingPhotos > 0 ? 'Требуют решения' : 'Очередь пуста')
                ->descriptionIcon($pendingPhotos > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pendingPhotos > 0 ? 'warning' : 'success')
                ->url(PhotoResource::getUrl('index')),

            Stat::make('Локации на модерации', (string) $pendingLocations)
                ->description($pendingLocations > 0 ? 'Требуют решения' : 'Очередь пуста')
                ->descriptionIcon($pendingLocations > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pendingLocations > 0 ? 'warning' : 'success')
                ->url(LocationResource::getUrl('index')),

            Stat::make('Опубликовано фото', (string) Photo::query()->where('status', PhotoStatus::Approved)->count())
                ->description('Показываются в галереях локаций')
                ->descriptionIcon('heroicon-m-photo')
                ->color('success')
                ->url(PhotoResource::getUrl('index')),

            Stat::make('Пользователи', (string) User::query()->count())
                ->description(User::query()->where('is_photographer', true)->count().' из них фотографы')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
        ];
    }
}
