<?php

namespace App\Services\Yandex\DTO;

/**
 * Итог одного полного прохода парсера.
 */
final readonly class ScrapeResult
{
    /**
     * @param  list<ReviewData>  $reviews
     */
    public function __construct(
        public OrganizationSnapshot $organization,
        public array $reviews,
    ) {}
}
