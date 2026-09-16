<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Models\City;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
