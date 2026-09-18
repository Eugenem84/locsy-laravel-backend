<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Подтверждение почты: пока адрес не подтверждён, функции аккаунта закрыты
 * (middleware `verified`), а ссылка из письма активирует аккаунт.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function signedVerifyUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    /**
     * Неподтверждённый пользователь не может пользоваться функциями аккаунта:
     * API отвечает 403 с понятным сообщением.
     */
    public function test_unverified_user_cannot_use_account_features(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->getJson('/api/favorites')
            ->assertStatus(403)
            ->assertJsonPath('email_verified', false);
    }

    /**
     * Подтверждённый пользователь получает доступ.
     */
    public function test_verified_user_can_use_account_features(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/favorites')->assertOk();
    }

    /**
     * Переход по подписанной ссылке подтверждает адрес, шлёт приветствие и
     * уводит на страницу SPA со статусом verified.
     */
    public function test_signed_link_verifies_email_and_redirects_to_spa(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->get($this->signedVerifyUrl($user))
            ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/#/verify-email?status=verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    /**
     * Подделанная/просроченная ссылка не подтверждает адрес, но уводит на SPA
     * со статусом invalid — без технической страницы 403.
     */
    public function test_tampered_link_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->signedVerifyUrl($user).'&tampered=1')
            ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/#/verify-email?status=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Повторная отправка письма подтверждения.
     */
    public function test_resend_sends_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson('/api/email/verification-notification')
            ->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /**
     * Для подтверждённого пользователя повторная отправка — без письма.
     */
    public function test_resend_is_noop_for_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/email/verification-notification')
            ->assertOk();

        Notification::assertNothingSent();
    }
}
