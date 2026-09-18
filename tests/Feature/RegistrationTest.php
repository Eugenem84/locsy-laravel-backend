<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Регистрация: после создания аккаунта уходит письмо со ссылкой на подтверждение
 * почты, а сам пользователь остаётся неподтверждённым до перехода по ссылке.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = [])
    {
        $city = City::factory()->create();

        return $this->postJson('/api/register', array_merge([
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'city_id' => $city->id,
        ], $overrides));
    }

    /**
     * Регистрация шлёт письмо-подтверждение, а аккаунт остаётся неактивным
     * (email_verified_at пуст), пока пользователь не перейдёт по ссылке.
     */
    public function test_registration_sends_verification_email_and_leaves_user_unverified(): void
    {
        Notification::fake();

        $this->register()->assertCreated();

        $user = User::where('email', 'ivan@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function ($notification, $channels) use ($user) {
                if (! in_array('mail', $channels, true)) {
                    return false;
                }

                $url = $notification->toMail($user)->actionUrl;

                return str_contains($url, '/email/verify/'.$user->id.'/')
                    && str_contains($url, 'signature=');
            }
        );
    }

    /**
     * Фотограф регистрируется так же: сначала подтверждение почты. Профиль
     * создаётся сразу, но функции аккаунта открываются после подтверждения.
     */
    public function test_photographer_registration_also_requires_verification(): void
    {
        Notification::fake();

        $this->register([
            'email' => 'photo@example.com',
            'role' => 'photographer',
            'display_name' => 'Иван Фото',
        ])->assertCreated();

        $user = User::where('email', 'photo@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /**
     * Неудачная валидация — аккаунт не создаётся и письмо не отправляется.
     */
    public function test_verification_email_is_not_sent_when_validation_fails(): void
    {
        Notification::fake();

        $this->register(['email' => 'not-an-email'])->assertStatus(422);

        Notification::assertNothingSent();
    }
}
