<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetUserPassword extends Command
{
    /**
     * @var string
     */
    protected $signature = 'locsy:user-password
        {email : Email пользователя}
        {--password= : Новый пароль (если не указан — сгенерируем)}';

    /**
     * @var string
     */
    protected $description = 'Меняет пароль пользователю вручную — страховка, когда письмо для сброса не доходит';

    public function handle(): int
    {
        $email = User::normalizeEmail($this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("Пользователь с email {$email} не найден");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));

        $user->forceFill(['password' => Hash::make($password)])->save();
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->info("Пароль обновлён для {$user->email}");
        $this->line("Новый пароль: {$password}");
        $this->warn('Токены и сессии пользователя отозваны — нужно войти заново.');

        return self::SUCCESS;
    }
}
