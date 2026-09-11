<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Яндекс отказал в доступе: 403 или 429.
 *
 * В отличие от капчи здесь даже страницу с проверкой не показывают — адрес
 * или его подсеть придержали. Повторять сразу бессмысленно: нужно дождаться
 * окончания блокировки, а при регулярном разборе — снизить темп или сменить
 * исходящий адрес.
 */
class AccessBlockedException extends YandexScraperException
{
    public static function status(int $status): self
    {
        return new self(
            $status === 429
                ? 'Яндекс ограничил частоту запросов. Попробуйте позже.'
                : 'Яндекс отказал в доступе. Возможно, адрес временно заблокирован.',
        );
    }

    public function errorCode(): string
    {
        return 'blocked';
    }

    public function httpStatus(): int
    {
        return 503;
    }
}
