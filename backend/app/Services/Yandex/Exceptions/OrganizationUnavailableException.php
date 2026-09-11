<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Карточка не открылась: организации нет, она удалена или Яндекс ответил ошибкой.
 */
class OrganizationUnavailableException extends YandexScraperException
{
    public static function status(int $status): self
    {
        return new self("Яндекс.Карты ответили кодом {$status}. Организация могла быть удалена или временно недоступна.");
    }

    public static function notAnOrganizationPage(): self
    {
        return new self('По этой ссылке не нашлось карточки организации. Проверьте, что ссылка ведёт на конкретную организацию.');
    }

    public function errorCode(): string
    {
        return 'organization_unavailable';
    }
}
