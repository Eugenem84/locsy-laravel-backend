<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Приводим email к нижнему регистру и запрещаем дубли по регистру.
     *
     * В PostgreSQL обычный unique(users.email) регистрозависимый: «Ivan@mail.ru»
     * и «ivan@mail.ru» спокойно жили как два аккаунта, а вход по «не тому»
     * регистру не находил пользователя. Нормализация в модели User + уникальный
     * индекс по lower(email) закрывают и то, и другое.
     */
    public function up(): void
    {
        DB::statement('UPDATE users SET email = lower(trim(email)) WHERE email <> lower(trim(email))');

        // Если дубли по регистру всё же есть (например, на боевой базе), индекс
        // создать нельзя — такие аккаунты нужно слить вручную, см. docs/ENVIRONMENTS.md
        $hasDuplicates = DB::table('users')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('count(*) > 1')
            ->exists();

        if (! $hasDuplicates) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (lower(email))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
