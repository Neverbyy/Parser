<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Яндекс показал капчу вместо данных.
 *
 * Отдаём 503: ошибка временная, имеет смысл повторить позже или с другого адреса.
 */
class CaptchaDetectedException extends YandexScraperException
{
    public static function make(): self
    {
        return new self('Яндекс запросил проверку на робота. Подождите немного и попробуйте снова.');
    }

    public function errorCode(): string
    {
        return 'captcha';
    }

    public function httpStatus(): int
    {
        return 503;
    }
}
