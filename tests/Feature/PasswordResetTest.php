<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SAME_ANSWER = 'Если такой адрес зарегистрирован, мы отправили письмо со ссылкой для сброса пароля.';

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'ivan@example.com',
            'password' => Hash::make('old-password-123'),
        ], $attributes));
    }

    /**
     * Запрос письма: уведомление уходит, а ссылка ведёт в SPA на страницу сброса
     * (роутер в hash-режиме) и содержит токен и email.
     */
    public function test_forgot_password_sends_reset_link_to_frontend(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->postJson('/api/forgot-password', ['email' => 'ivan@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::SAME_ANSWER);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function ($notification, $channels) use ($user) {
                if (! in_array('mail', $channels, true)) {
                    return false;
                }

                $url = $notification->toMail($user)->actionUrl;

                return str_starts_with($url, config('app.frontend_url').'/#/reset-password')
                    && str_contains($url, 'token='.$notification->token)
                    && str_contains($url, urlencode($user->email));
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'ivan@example.com']);
    }

    /**
     * Незнакомый адрес: ответ тот же самый и письма нет — по ответу нельзя
     * определить, есть ли такой пользователь в сервисе.
     */
    public function test_forgot_password_hides_whether_email_exists(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::SAME_ANSWER);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * Сброс по валидному токену: пароль меняется, токены Sanctum отзываются,
     * одноразовый токен сброса удаляется.
     */
    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->makeUser();
        $user->createToken('api-token');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ivan@example.com',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ivan@example.com']);
    }

    /**
     * После смены пароля владельцу уходит уведомление: если смену инициировал
     * не он, это шанс вовремя заметить взлом.
     */
    public function test_password_reset_notifies_user_about_change(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ivan@example.com',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertOk();

        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    /**
     * Неверный токен — 422, пароль не меняется.
     */
    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/reset-password', [
            'token' => 'wrong-token',
            'email' => 'ivan@example.com',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ссылка недействительна или устарела. Запросите письмо заново.');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    /**
     * Короткий пароль не принимается — правила те же, что при регистрации.
     */
    public function test_reset_password_requires_min_length(): void
    {
        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ivan@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /**
     * Форму запроса письма нельзя использовать для спама: 6-й запрос за минуту — 429.
     */
    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        $this->makeUser();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/forgot-password', ['email' => 'ivan@example.com'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'ivan@example.com'])->assertStatus(429);
    }

    /**
     * Страховка на случай, когда письмо не доходит: админский сброс пароля
     * artisan-командой (пароль генерируется, если не передан).
     */
    public function test_artisan_command_sets_user_password(): void
    {
        $user = $this->makeUser();
        $user->createToken('api-token');

        $this->artisan('locsy:user-password', [
            'email' => 'ivan@example.com',
            '--password' => 'brand-new-password',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Команда не должна «молча» ничего делать для несуществующего адреса.
     */
    public function test_artisan_command_fails_for_unknown_user(): void
    {
        $this->artisan('locsy:user-password', ['email' => 'nobody@example.com'])
            ->assertFailed();
    }

    /**
     * Email нормализуется: регистрация с «Ivan@Mail.RU» создаёт аккаунт
     * «ivan@mail.ru», и вход в другом регистре находит его.
     */
    public function test_email_is_normalized_on_registration_and_login(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/register', [
            'name' => 'Иван',
            'email' => 'Ivan@Mail.RU',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'city_id' => $city->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ivan@mail.ru']);

        $this->postJson('/api/login', [
            'email' => 'IVAN@mail.ru',
            'password' => 'secret-password',
        ])->assertOk();
    }
}
