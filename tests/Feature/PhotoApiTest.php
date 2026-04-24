<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест на АВТОРИЗАЦИЮ.
     * Проверяет, что пользователь не может удалить чужую фотографию.
     */
    public function test_user_cannot_delete_another_users_photo()
    {
        // 1. Подготовка (Arrange)
        // Создаем двух пользователей
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        // Создаем локацию от имени первого пользователя
        $location = Location::factory()->create(['user_id' => $userOne->id]);

        // Создаем фотографию для этой локации, также принадлежащую первому пользователю
        $photo = Photo::factory()->create([
            'location_id' => $location->id,
            'user_id' => $userOne->id,
        ]);

        // 2. Действие (Act)
        // Пытаемся удалить фотографию от имени ВТОРОГО пользователя
        $response = $this->actingAs($userTwo)->deleteJson('/api/photos/' . $photo->id);

        // 3. Проверка (Assert)
        // Ожидаем статус 403 Forbidden (или 404 Not Found, если политика скрывает существование ресурса)
        // 403 является более корректным в данном случае.
        $response->assertStatus(403);

        // Дополнительно убедимся, что фотография не была удалена из базы данных
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
    }
}
