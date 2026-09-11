<?php

namespace App\Services\Yandex\Exceptions;

use RuntimeException;

/**
 * Базовая ошибка работы с Яндекс.Картами.
 *
 * Сообщение пишется сразу на русском и пригодно для показа в интерфейсе,
 * errorCode() даёт фронту стабильный ключ для ветвления, httpStatus() —
 * код ответа. Маппинг в JSON живёт в bootstrap/app.php.
 */
class YandexScraperException extends RuntimeException
{
    public function errorCode(): string
    {
        return 'yandex_error';
    }

    public function httpStatus(): int
    {
        return 502;
    }
}
