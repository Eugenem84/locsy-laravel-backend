<?php

namespace Database\Seeders;

use App\Enums\LocationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Базовый сидер «популярные места города»: общая механика для городских наборов
 * (Москва, Ярославль, дальше — любой другой город).
 *
 * Сидер идемпотентный: место ищется по паре (city_id, name), повторный запуск
 * обновляет запись, а не создаёт дубликат. Ничего не удаляет и не делает truncate,
 * поэтому его безопасно прогонять на живой базе:
 *
 *   php artisan db:seed --class=MoscowParksSeeder
 *
 * Локации публикуются сразу со статусом approved — публичный каталог
 * (LocationController) показывает только одобренные.
 */
abstract class CityPlacesSeeder extends Seeder
{
    /** geonameid города в GeoNames (см. database/seeders/citiesTXT/cities15000.txt). */
    abstract protected function cityGeonameID(): int;

    /** Слаг города — запасной вариант поиска, если geonameid не совпал. */
    abstract protected function citySlug(): string;

    /** Название города для сообщений в консоли. */
    abstract protected function cityLabel(): string;

    /**
     * Справочник мест: название, описание, координаты и категории.
     *
     * @return array<int, array{name: string, description: string, latitude: string, longitude: string, categories: string[]}>
     */
    abstract protected function places(): array;

    public function run(): void
    {
        $city = $this->city();

        if (! $city) {
            $this->command?->error(sprintf(
                '%s не найден в cities — сначала выполните CitiesTableSeeder.',
                $this->cityLabel()
            ));

            return;
        }

        $places = $this->places();

        foreach ($places as $place) {
            $location = Location::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'name' => $place['name'],
                ],
                [
                    'description' => $place['description'],
                    'latitude' => $place['latitude'],
                    'longitude' => $place['longitude'],
                    'status' => LocationStatus::Approved,
                ]
            );

            $location->categories()->sync($this->categoryIds($place['categories']));
        }

        $this->command?->info(sprintf(
            '%s: загружено мест — %d (категорий задействовано: %d).',
            $this->cityLabel(),
            count($places),
            count($this->usedCategories())
        ));
    }

    /**
     * Город по geonameid, с запасным поиском по слагу и стране.
     */
    protected function city(): ?City
    {
        return City::query()
            ->where('geonameid', $this->cityGeonameID())
            ->orWhere(fn ($query) => $query
                ->where('slug', $this->citySlug())
                ->where('country_code', 'RU'))
            ->first();
    }

    /**
     * @param  string[]  $names
     * @return int[]
     */
    protected function categoryIds(array $names): array
    {
        return array_map(
            fn (string $name) => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            )->id,
            $names
        );
    }

    /**
     * @return string[]
     */
    protected function usedCategories(): array
    {
        $names = [];

        foreach ($this->places() as $place) {
            $names = array_merge($names, $place['categories']);
        }

        return array_values(array_unique($names));
    }
}
