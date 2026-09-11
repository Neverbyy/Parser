<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Ссылка не похожа на карточку организации в Яндекс.Картах.
 */
class InvalidOrganizationUrlException extends YandexScraperException
{
    public static function make(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'invalid_url';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
