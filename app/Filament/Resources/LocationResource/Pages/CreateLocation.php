<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Enums\LocationStatus;
use App\Filament\Resources\LocationResource;
use App\Settings\ModerationSettings; // Импортируем настройки
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $settings = app(ModerationSettings::class); // Получаем доступ к настройкам

        if ($settings->location_moderation_enabled) {
            $data['status'] = LocationStatus::Pending->value;
        } else {
            $data['status'] = LocationStatus::Approved->value;
        }

        return $data;
    }
}
