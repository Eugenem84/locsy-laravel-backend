<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Models\City;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CityApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Корень API уводит на SPA-фронтенд, а не отдаёт контент.
     */
    public function test_root_redirects_to_frontend(): void
    {
        $this->get('/')->assertRedirect(config('app.frontend_url'));
    }

    /**
     * Публичный список городов доступен без авторизации.
     * Контроллер отдаёт только города РФ с населением > 100 тыс.
     */
    public function test_cities_endpoint_is_public(): void
    {
        City::factory()->create([
            'name' => 'Москва',
            'slug' => 'moskva',
            'country_code' => 'RU',
            'population' => 12_000_000,
        ]);

        $this->getJson('/api/cities')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'moskva']);
    }

    /**
     * Список городов отсортирован по убыванию населения:
     * самый крупный город должен стоять первым.
     */
    public function test_cities_are_ordered_by_population_desc(): void
    {
        $small = City::factory()->create([
            'name' => 'Малый',
            'slug' => 'malyy',
            'country_code' => 'RU',
            'population' => 150_000,
        ]);
        $medium = City::factory()->create([
            'name' => 'Средний',
            'slug' => 'sredniy',
            'country_code' => 'RU',
            'population' => 1_000_000,
        ]);
        $big = City::factory()->create([
            'name' => 'Большой',
            'slug' => 'bolshoy',
            'country_code' => 'RU',
            'population' => 10_000_000,
        ]);

        $response = $this->getJson('/api/cities')->assertOk();

        $this->assertSame($big->id, $response->json('0.id'));
        $this->assertSame($medium->id, $response->json('1.id'));
        $this->assertSame($small->id, $response->json('2.id'));
    }

    /**
     * Города с населением меньше 100 тыс. не попадают в справочник,
     * поэтому на порядок они влиять не должны.
     */
    public function test_cities_with_small_population_are_excluded(): void
    {
        City::factory()->create([
            'name' => 'Крупный',
            'slug' => 'krupnyy',
            'country_code' => 'RU',
            'population' => 500_000,
        ]);
        City::factory()->create([
            'name' => 'Мелкий',
            'slug' => 'melkiy',
            'country_code' => 'RU',
            'population' => 99_999,
        ]);

        $response = $this->getJson('/api/cities')->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame('krupnyy', $response->json('0.slug'));
    }

    /**
     * Локации города отдаются публично, но только со статусом «одобрено».
     */
    public function test_locations_of_city_returns_only_approved(): void
    {
        $city = City::factory()->create();

        $approved = Location::factory()->create([
            'city_id' => $city->id,
            'status' => LocationStatus::Approved,
        ]);
        Location::factory()->create([
            'city_id' => $city->id,
            'status' => LocationStatus::Pending,
        ]);

        $response = $this->getJson("/api/locations?city_id={$city->id}")->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame($approved->id, $response->json('0.id'));
    }

    /**
     * Для экзонимов (Moscow) asciiname не является транслитерацией русского имени,
     * поэтому название берём из явного списка, а не из автоподбора по alternatenames.
     * Регион Москвы тоже задаётся явно: в GeoNames её «регион» — сама Москва.
     */
    public function test_city_name_uses_override_for_exonym(): void
    {
        $admin1Id = DB::table('admin1_codes')->insertGetId([
            'country_code' => 'RU',
            'admin1_code' => '48',
            'name' => 'Moscow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin1_code_translations')->insert([
            'admin1_code_id' => $admin1Id,
            'locale' => 'ru',
            'name' => 'Москва',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        City::factory()->create([
            'name' => 'Moscow',
            'slug' => 'moscow',
            'geonameid' => 524901,
            'country_code' => 'RU',
            'population' => 10_000_000,
            'admin1_code' => '48',
            'asciiname' => 'Moscow',
            // По схожести с asciiname «Москох» выигрывает у правильного варианта
            'alternatenames' => 'Moscow,Moskva,Москва,Москох',
        ]);

        $response = $this->getJson('/api/cities')->assertOk();

        $this->assertSame('Москва (Московская область)', $response->json('0.name'));
    }

    /**
     * Города нет в списке исключений: русское имя по-прежнему выбирается
     * из alternatenames по схожести с asciiname.
     */
    public function test_city_name_is_picked_from_alternate_names(): void
    {
        City::factory()->create([
            'name' => 'Voronezh',
            'slug' => 'voronezh',
            'geonameid' => 990001,
            'country_code' => 'RU',
            'population' => 1_000_000,
            'asciiname' => 'Voronezh',
            'alternatenames' => 'Voronezh,Воронеж,Воронежская область',
        ]);

        $response = $this->getJson('/api/cities')->assertOk();

        $this->assertSame('Воронеж', $response->json('0.name'));
    }

    /**
     * Регион в скобках не дублирует название города (Москва и Санкт-Петербург —
     * города федерального значения, их «регион» в GeoNames одноимёнен),
     * но остаётся у городов, которые региону не одноимённы.
     */
    public function test_region_is_not_duplicated_in_city_name(): void
    {
        $admin1Id = DB::table('admin1_codes')->insertGetId([
            'country_code' => 'RU',
            'admin1_code' => '68',
            'name' => 'Test Region',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin1_code_translations')->insert([
            'admin1_code_id' => $admin1Id,
            'locale' => 'ru',
            'name' => 'Тестгород',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Город одноимёнен региону — подпись не нужна
        City::factory()->create([
            'name' => 'Testgorod',
            'slug' => 'testgorod',
            'geonameid' => 990002,
            'country_code' => 'RU',
            'population' => 500_000,
            'admin1_code' => '68',
            'asciiname' => 'Testgorod',
            'alternatenames' => 'Testgorod,Тестгород',
        ]);

        // Другой город того же региона: подпись региона нужна, город ≠ регион
        City::factory()->create([
            'name' => 'Second',
            'slug' => 'second',
            'geonameid' => 990003,
            'country_code' => 'RU',
            'population' => 400_000,
            'admin1_code' => '68',
            'asciiname' => 'Second',
            'alternatenames' => 'Second,Второй',
        ]);

        $response = $this->getJson('/api/cities')->assertOk();

        $this->assertSame('Тестгород', $response->json('0.name'));
        $this->assertSame('Второй (Тестгород)', $response->json('1.name'));
    }
}
