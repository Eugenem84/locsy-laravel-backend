<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    /**
     * Запрос письма со ссылкой для сброса пароля.
     *
     * Ответ всегда одинаковый: по нему нельзя выяснить, зарегистрирован адрес
     * или нет (иначе это утечка того, какие почты есть в сервисе).
     */
    public function sendResetLink(Request $request)
    {
        $request->merge(['email' => User::normalizeEmail($request->input('email'))]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));

            // Повторное письмо раньше чем через 60 секунд Laravel не отправляет.
            // Для клиента это неразличимо, поэтому просто отмечаем в логе.
            if ($status === Password::RESET_THROTTLED) {
                Log::info('Password reset link is throttled', ['email' => $request->input('email')]);
            }
        } catch (Throwable $e) {
            // Технические детали — только в лог, клиенту нейтральный ответ
            Log::error('Password reset link error: '.$e->getMessage(), ['exception' => $e]);
        }

        return response()->json([
            'message' => 'Если такой адрес зарегистрирован, мы отправили письмо со ссылкой для сброса пароля.',
        ]);
    }

    /**
     * Установка нового пароля по токену из письма.
     */
    public function reset(Request $request)
    {
        $request->merge(['email' => User::normalizeEmail($request->input('email'))]);

        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill(['password' => Hash::make($password)])->save();

                    // После смены пароля ранее выданные токены и сессии недействительны
                    $user->tokens()->delete();

                    if (config('session.driver') === 'database') {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    }

                    event(new PasswordReset($user));
                }
            );
        } catch (Throwable $e) {
            Log::error('Password reset error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Не удалось сменить пароль. Попробуйте ещё раз.',
            ], 500);
        }

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Ссылка недействительна или устарела. Запросите письмо заново.',
            ], 422);
        }

        return response()->json([
            'message' => 'Пароль обновлён — войдите с новым паролем.',
        ]);
    }
}
