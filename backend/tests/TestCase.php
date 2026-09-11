<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum считает запрос «своим» (и подключает сессию) только если
        // Origin или Referer входят в SANCTUM_STATEFUL_DOMAINS. В тестах таких
        // заголовков нет, поэтому подставляем адрес SPA — иначе проверяется
        // не тот режим аутентификации, в котором приложение реально работает.
        $this->withHeader('Origin', (string) config('app.frontend_url'));
    }
}
