<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationController extends Controller
{
    /**
     * Подтверждение почты по ссылке из письма.
     *
     * Роут подписанный и с истечением (проверяем через `hasValidSignature`),
     * а в конце уводим пользователя на страницу SPA с понятным статусом:
     * ссылку открывает браузер из письма, JSON ему не подходит.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $status = 'invalid';

        if ($request->hasValidSignature()) {
            $user = User::find($id);

            // Хэш страхует от подмены id в ссылке
            if ($user && hash_equals($hash, sha1($user->getEmailForVerification()))) {
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                    event(new Verified($user));
                    $this->sendWelcome($user);
                }

                $status = 'verified';
            }
        }

        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/#/verify-email?status='.$status
        );
    }

    /**
     * Повторная отправка письма с ссылкой подтверждения.
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Почта уже подтверждена.'], 200);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(
            ['message' => 'Письмо отправлено повторно. Проверьте почту — и папку «Спам».'],
            202
        );
    }

    /**
     * Приветствие после активации. Письмо не должно ломать сам переход по ссылке.
     */
    private function sendWelcome(User $user): void
    {
        try {
            $user->notify(new WelcomeNotification);
        } catch (Throwable $e) {
            Log::warning('Welcome email failed: '.$e->getMessage(), ['user_id' => $user->id]);
        }
    }
}
