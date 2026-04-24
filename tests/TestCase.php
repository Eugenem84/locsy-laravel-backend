<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('TEST_DB_PRINTED')) {
            // Выводим сообщение жирным зеленым цветом
            echo "\n\033[1;32mTests are using database: " . DB::connection()->getDatabaseName() . "\033[0m\n";
            define('TEST_DB_PRINTED', true);
        }
    }
}
