<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * База для тестов зафиксирована жёстко: переменные окружения контейнера/.env
     * (APP_ENV=local, DB_DATABASE=locsy) перебивают phpunit.xml, и без этой
     * защиты RefreshDatabase мог бы очистить рабочую базу.
     */
    protected const TESTING_DATABASE = 'locsy_testing';

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        config(["database.connections.{$connection}.database" => static::TESTING_DATABASE]);

        // Если соединение уже было создано (например, из-за кеша конфига),
        // сбрасываем его, чтобы оно перечитало тестовую базу.
        DB::purge($connection);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $connection = config('database.default');
        $configured = config("database.connections.{$connection}.database");

        if ($database !== static::TESTING_DATABASE) {
            throw new RuntimeException(sprintf(
                "Тесты подключены к базе '%s' (в конфиге '%s'), а ожидается '%s'. ".
                'Прогон остановлен, чтобы не повредить рабочие данные. '.
                'Проверьте: php artisan config:clear && php artisan test.',
                $database,
                $configured,
                static::TESTING_DATABASE
            ));
        }
    }
}
