<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Внутренний API отозвался, но полезной нагрузки в ответе нет.
 */
class EmptyResponseException extends YandexScraperException
{
    public static function make(): self
    {
        return new self('Яндекс вернул пустой ответ вместо списка отзывов. Попробуйте повторить позже.');
    }

    public function errorCode(): string
    {
        return 'empty_response';
    }
}
