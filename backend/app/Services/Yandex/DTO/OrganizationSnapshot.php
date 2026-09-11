<?php

namespace App\Services\Yandex\DTO;

/**
 * Агрегированные данные карточки на момент парсинга.
 *
 * ratingsCount и reviewsCount — разные величины: первое считает всех, кто
 * поставил оценку, второе — только тех, кто написал текст.
 */
final readonly class OrganizationSnapshot
{
    public function __construct(
        public string $yandexId,
        public string $url,
        public ?string $name = null,
        public ?string $address = null,
        public ?float $rating = null,
        public ?int $ratingsCount = null,
        public ?int $reviewsCount = null,
    ) {}
}
