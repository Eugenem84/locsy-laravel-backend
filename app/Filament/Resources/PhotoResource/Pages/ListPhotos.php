<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Enums\PhotoStatus;
use App\Filament\Resources\PhotoResource;
use App\Models\Photo;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('На модерации')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStatus::Pending))
                ->badge(Photo::query()->where('status', PhotoStatus::Pending)->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Одобренные')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStatus::Approved)),
            'rejected' => Tab::make('Отклонённые')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStatus::Rejected)),
            'all' => Tab::make('Все'),
        ];
    }
}
