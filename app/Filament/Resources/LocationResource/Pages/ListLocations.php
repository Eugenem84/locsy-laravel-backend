<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Enums\LocationStatus;
use App\Filament\Resources\LocationResource;
use App\Models\Location;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все'),
            'pending' => Tab::make('На модерации')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationStatus::Pending))
                ->badge(Location::query()->where('status', LocationStatus::Pending)->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Одобренные')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationStatus::Approved)),
            'rejected' => Tab::make('Отклоненные')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationStatus::Rejected)),
        ];
    }
}
