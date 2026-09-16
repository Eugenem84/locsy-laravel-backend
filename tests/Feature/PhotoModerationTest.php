<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Enums\PhotoStatus;
use App\Models\City;
use App\Models\Location;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Модерация фотографий — ключевая часть продукта:
 * в публичный каталог попадает только контент, одобренный модератором.
 */
class PhotoModerationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedLocation(): Location
    {
        return Location::factory()->create(['status' => LocationStatus::Approved]);
    }

    public function test_new_photo_is_pending_and_hidden_from_public_feed(): void
    {
        $location = $this->approvedLocation();
        $photo = Photo::factory()->create(['location_id' => $location->id]);

        $this->assertSame(PhotoStatus::Pending, $photo->fresh()->status);

        $this->getJson("/api/location/{$location->id}")
            ->assertOk()
            ->assertJsonCount(0, 'photos');
    }

    public function test_only_approved_photos_are_public(): void
    {
        $location = $this->approvedLocation();
        Photo::factory()->create(['location_id' => $location->id, 'status' => PhotoStatus::Approved]);
        Photo::factory()->create(['location_id' => $location->id, 'status' => PhotoStatus::Rejected]);
        Photo::factory()->create(['location_id' => $location->id, 'status' => PhotoStatus::Pending]);

        $response = $this->getJson("/api/location/{$location->id}")->assertOk();

        $this->assertCount(1, $response->json('photos'));
    }

    public function test_author_sees_status_and_rejection_reason_in_my_photos(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'status' => PhotoStatus::Rejected,
            'moderation_note' => 'Слишком размыто',
        ]);

        $this->actingAs($user)
            ->getJson('/api/user/photos')
            ->assertOk()
            ->assertJsonPath('0.status', 'rejected')
            ->assertJsonPath('0.moderation_note', 'Слишком размыто')
            ->assertJsonFragment(['id' => $photo->id]);
    }

    public function test_photographer_profile_returns_only_approved_photos_and_shooting_spots(): void
    {
        $user = User::factory()->create(['is_photographer' => true]);
        $user->photographerProfile()->create([
            'display_name' => 'Тестовый фотограф',
            'is_active' => true,
        ]);

        $location = $this->approvedLocation();
        Photo::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'status' => PhotoStatus::Approved,
        ]);
        Photo::factory()->create([
            'user_id' => $user->id,
            'status' => PhotoStatus::Pending,
        ]);

        $this->getJson("/api/photographers/{$user->id}")
            ->assertOk()
            ->assertJsonPath('photos_count', 1)
            ->assertJsonPath('shooting_spots.0.id', $location->id);
    }

    public function test_registration_as_photographer_creates_profile_and_logs_in(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'city_id' => $city->id,
            'role' => 'photographer',
            'display_name' => 'Иван Фото',
            'work_types' => ['Свадьба'],
        ])
            ->assertCreated()
            ->assertJsonPath('user.photographer_profile.display_name', 'Иван Фото');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('photographer_profiles', [
            'display_name' => 'Иван Фото',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'ivan@example.com',
            'is_photographer' => true,
        ]);
    }

    public function test_locations_by_bounds_accepts_inverted_corners(): void
    {
        $location = $this->approvedLocation();

        $swLat = (float) $location->latitude - 1;
        $swLng = (float) $location->longitude - 1;
        $neLat = (float) $location->latitude + 1;
        $neLng = (float) $location->longitude + 1;

        // Углы специально переданы в обратном порядке — API должен нормализовать диапазон
        $this->getJson('/api/locations/by-bounds?'.http_build_query([
            'sw_lat' => $neLat,
            'sw_lng' => $neLng,
            'ne_lat' => $swLat,
            'ne_lng' => $swLng,
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $location->id]);
    }
}
