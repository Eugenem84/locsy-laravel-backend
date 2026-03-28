<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class MapPicker extends Field
{
    protected string $view = 'filament.forms.components.map-picker';

    protected string $latitudeStatePath;
    protected string $longitudeStatePath;

    public static function make(string $name): static
    {
        return parent::make($name)
            ->latitude('latitude')
            ->longitude('longitude');
    }

    public function latitude(string $path): static
    {
        $this->latitudeStatePath = $path;

        return $this;
    }

    public function longitude(string $path): static
    {
        $this->longitudeStatePath = $path;

        return $this;
    }

    public function getLatitudeStatePath(): string
    {
        return $this->latitudeStatePath;
    }

    public function getLongitudeStatePath(): string
    {
        return $this->longitudeStatePath;
    }
}
