<?php

namespace App\Services\Yandex\DTO;

/**
 * Всё, что нужно вытащить из HTML карточки.
 *
 * Кроме агрегатов это ещё и «ключи от API»: csrfToken и sessionId Яндекс
 * кладёт в SSR-состояние страницы, без них внутренний эндпоинт отзывов
 * отвечает 400.
 */
final readonly class CardPage
{
    public function __construct(
        public OrganizationSnapshot $organization,
        public string $csrfToken,
        public string $sessionId,
        public string $locale,
    ) {}

    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->organization, $csrfToken, $this->sessionId, $this->locale);
    }
}
