<?php

namespace App\Services\Yandex\DTO;

/**
 * Разобранная ссылка на карточку организации.
 */
final readonly class ParsedOrganizationUrl
{
    public function __construct(
        public string $yandexId,
        public string $originalUrl,
        public string $cardUrl,
    ) {}
}
