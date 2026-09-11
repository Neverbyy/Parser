<?php

namespace App\Services\Yandex\DTO;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Один отзыв в том виде, в каком его отдаёт внутренний API Яндекса.
 */
final readonly class ReviewData
{
    public function __construct(
        public string $id,
        public ?string $authorName = null,
        public ?string $authorAvatarUrl = null,
        public ?int $rating = null,
        public ?string $text = null,
        public ?CarbonImmutable $publishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): ?self
    {
        $id = $raw['reviewId'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $author = is_array($raw['author'] ?? null) ? $raw['author'] : [];

        return new self(
            id: $id,
            authorName: self::nullableString($author['name'] ?? null),
            authorAvatarUrl: self::avatarUrl($author['avatarUrl'] ?? null),
            rating: isset($raw['rating']) && is_numeric($raw['rating']) ? (int) $raw['rating'] : null,
            text: self::nullableString($raw['text'] ?? null),
            publishedAt: self::parseDate($raw['updatedTime'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Яндекс отдаёт шаблон вида .../{size}, подставляем конкретный размер.
     */
    private static function avatarUrl(mixed $value): ?string
    {
        $url = self::nullableString($value);

        return $url === null ? null : str_replace('{size}', 'islands-68', $url);
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
