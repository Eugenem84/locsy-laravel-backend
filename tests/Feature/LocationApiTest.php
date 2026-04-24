<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест на АУТЕНТИФИКАЦИЮ.
     * Проверяет, что неаутентифицированный пользователь ("гость") не может создать локацию.
     */
    public function test_guest_cannot_create_location()
    {
        // 1. Подготовка (Arrange) - не нужна, так как мы "гость"

        // 2. Действие (Act)
        // Выполняем POST-запрос БЕЗ `actingAs()`, то есть как неаутентифицированный пользователь.
        $response = $this->postJson('/api/locations', [
            'name' => 'Some Name',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        // 3. Проверка (Assert)
        // Проверяем, что сервер вернул статус 401 Unauthorized.
        $response->assertStatus(401);
    }

    /**
     * Тест на ВАЛИДАЦИЮ.
     * Проверяет, что API возвращает ошибку валидации,
     * если попытаться создать локацию с пустым названием.
     */
    public function test_cannot_create_location_without_a_name()
    {
        // 1. Подготовка (Arrange)
        // Создаем пользователя, от имени которого будем делать запрос.
        $user = User::factory()->create();

        // Готовим данные для отправки. Название намеренно пустое.
        $locationData = [
            'name' => '',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ];

        // 2. Действие (Act)
        // Выполняем POST-запрос от имени созданного пользователя.
        $response = $this->actingAs($user)->postJson('/api/locations', $locationData);

        // 3. Проверка (Assert)
        // Проверяем, что сервер вернул статус 422 (Unprocessable Entity).
        $response->assertStatus(422);

        // Проверяем, что в JSON-ответе с ошибками есть ключ "name".
        $response->assertJsonValidationErrors('name');
    }
}
