<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\ParsedOrganizationUrl;
use App\Services\Yandex\Exceptions\InvalidOrganizationUrlException;

/**
 * Валидация ссылки на организацию и извлечение её идентификатора.
 *
 * Яндекс раздаёт карточку по десятку разных адресов — со слагом и без, на
 * зеркалах с другими доменами и путями (yandex.com.tr/harita/...), через
 * /profile/, через параметр oid в поисковой выдаче. Идентификатор при этом
 * всегда один и тот же, поэтому любую ссылку приводим к каноническому виду
 * https://yandex.ru/maps/org/{id}/reviews/ — он проверенно работает для
 * организаций с любого зеркала.
 */
final class YandexMapsUrlParser
{
    /**
     * Домены Яндекса, на которых встречаются карточки организаций.
     */
    private const HOST_PATTERN = '/(?:^|\.)(?:yandex\.(?:ru|com|by|kz|uz|eu|com\.tr|com\.ge|com\.am|co\.il)|ya\.ru)$/i';

    /**
     * Пути, из которых достаётся идентификатор организации.
     *
     * @var list<string>
     */
    private const ID_PATTERNS = [
        '#^/(?:maps|harita)/org/(?:[^/]+/)?(\d+)#i',
        '#^/org/(?:[^/]+/)?(\d+)#i',
        '#^/profile/(?:[^/]+/)?(\d+)#i',
    ];

    /**
     * Короткая ссылка «Поделиться»: идентификатора в ней нет, нужен редирект.
     */
    private const SHORT_LINK_PATTERN = '#^/(?:maps|harita)/-/#i';

    public function parse(string $url): ParsedOrganizationUrl
    {
        $normalized = $this->normalize($url);
        $parts = parse_url($normalized);

        if ($parts === false || ! isset($parts['host'])) {
            throw InvalidOrganizationUrlException::make('Не удалось разобрать ссылку. Вставьте адрес целиком, вместе с https://.');
        }

        if (preg_match(self::HOST_PATTERN, $parts['host']) !== 1) {
            throw InvalidOrganizationUrlException::make('Ссылка должна вести на Яндекс.Карты.');
        }

        $id = $this->extractId($parts);

        if ($id === null) {
            throw InvalidOrganizationUrlException::make(
                'В ссылке не нашёлся идентификатор организации. Откройте карточку компании на Яндекс.Картах и скопируйте адрес из строки браузера.'
            );
        }

        return new ParsedOrganizationUrl(
            yandexId: $id,
            originalUrl: $normalized,
            cardUrl: "https://yandex.ru/maps/org/{$id}/reviews/",
        );
    }

    /**
     * Короткие ссылки вида https://yandex.ru/maps/-/CDxxxxx разворачиваются
     * только редиректом, поэтому их обрабатывает вызывающий код.
     */
    public function isShortLink(string $url): bool
    {
        $parts = parse_url($this->normalize($url));

        if ($parts === false || ! isset($parts['host'], $parts['path'])) {
            return false;
        }

        return preg_match(self::HOST_PATTERN, $parts['host']) === 1
            && preg_match(self::SHORT_LINK_PATTERN, $parts['path']) === 1;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function extractId(array $parts): ?string
    {
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';

        foreach (self::ID_PATTERNS as $pattern) {
            if (preg_match($pattern, $path, $matches) === 1) {
                return $matches[1];
            }
        }

        // Выдача поиска по карте: организация указана параметром oid.
        $query = is_string($parts['query'] ?? null) ? $parts['query'] : '';
        parse_str($query, $params);

        $oid = $params['oid'] ?? null;

        return is_string($oid) && preg_match('/^\d+$/', $oid) === 1 ? $oid : null;
    }

    private function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw InvalidOrganizationUrlException::make('Укажите ссылку на организацию.');
        }

        // Пользователи часто копируют адрес без протокола.
        if (preg_match('#^https?://#i', $url) !== 1) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }
}
