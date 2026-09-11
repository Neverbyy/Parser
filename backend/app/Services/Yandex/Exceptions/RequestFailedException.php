<?php

namespace App\Services\Yandex\Exceptions;

use Throwable;

/**
 * Не удалось достучаться до Яндекса: таймаут, обрыв соединения, DNS.
 */
class RequestFailedException extends YandexScraperException
{
    public static function from(Throwable $previous): self
    {
        return new self(
            'Не удалось связаться с Яндекс.Картами. Проверьте соединение и попробуйте ещё раз.',
            0,
            $previous
        );
    }

    public function errorCode(): string
    {
        return 'request_failed';
    }

    public function httpStatus(): int
    {
        return 504;
    }
}
