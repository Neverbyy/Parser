<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\CardPage;
use App\Services\Yandex\DTO\OrganizationSnapshot;
use App\Services\Yandex\DTO\ParsedOrganizationUrl;
use App\Services\Yandex\Exceptions\MarkupChangedException;
use App\Services\Yandex\Exceptions\OrganizationUnavailableException;

/**
 * Разбор HTML карточки организации.
 *
 * Страница отдаётся server-side отрендеренной, и всё нужное лежит в ней сразу
 * в двух местах:
 *
 *   - <script class="state-view"> — состояние приложения. Отсюда берутся
 *     csrfToken и sessionId (без них API отзывов отвечает 400), а также
 *     карточка организации со структурой ratingData;
 *   - schema.org-микроразметка в теле страницы — те же три числа в виде
 *     <meta itemprop="ratingValue|ratingCount|reviewCount">.
 *
 * Основной источник — state-view: там числа лежат типизированными. Микроразметка
 * работает запасным вариантом, если Яндекс поменяет структуру состояния, — два
 * независимых источника заметно снижают шанс, что парсер встанет целиком.
 */
final class CardPageParser
{
    private const STATE_PATTERN = '#<script[^>]+class="state-view"[^>]*>(.*?)</script>#s';

    public function parse(string $html, ParsedOrganizationUrl $url): CardPage
    {
        $state = $this->decodeState($html);

        $config = $state['config'] ?? null;

        if (! is_array($config)) {
            throw MarkupChangedException::missing('конфигурацию страницы');
        }

        $csrfToken = $config['csrfToken'] ?? null;

        if (! is_string($csrfToken) || $csrfToken === '') {
            throw MarkupChangedException::missing('CSRF-токен');
        }

        $sessionId = data_get($config, 'counters.analytics.sessionId');

        if (! is_string($sessionId) || $sessionId === '') {
            throw MarkupChangedException::missing('идентификатор сессии');
        }

        $locale = is_string($config['locale'] ?? null) && $config['locale'] !== ''
            ? $config['locale']
            : (string) config('yandex.locale');

        return new CardPage(
            organization: $this->buildSnapshot($html, $url, $this->findBusiness($state)),
            csrfToken: $csrfToken,
            sessionId: $sessionId,
            locale: $locale,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeState(string $html): array
    {
        if (preg_match(self::STATE_PATTERN, $html, $matches) !== 1) {
            throw MarkupChangedException::missing('состояние приложения');
        }

        $decoded = json_decode($matches[1], true);

        if (! is_array($decoded)) {
            throw MarkupChangedException::missing('корректный JSON состояния');
        }

        return $decoded;
    }

    /**
     * Карточка организации в состоянии страницы.
     *
     * Если её нет, ссылка вела не на организацию (например, на город или
     * на удалённую карточку) — это ошибка данных, а не разметки.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function findBusiness(array $state): array
    {
        $items = data_get($state, 'stack.0.results.items');

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'business') {
                    return $item;
                }
            }
        }

        throw OrganizationUnavailableException::notAnOrganizationPage();
    }

    /**
     * @param  array<string, mixed>  $business
     */
    private function buildSnapshot(string $html, ParsedOrganizationUrl $url, array $business): OrganizationSnapshot
    {
        $rating = data_get($business, 'ratingData.ratingValue');
        $ratingsCount = data_get($business, 'ratingData.ratingCount');
        $reviewsCount = data_get($business, 'ratingData.reviewCount');

        return new OrganizationSnapshot(
            yandexId: $url->yandexId,
            url: $url->cardUrl,
            name: $this->string($business['title'] ?? null) ?? $this->heading($html),
            address: $this->string($business['address'] ?? null),

            // Округление обязательно: в состоянии лежит float со шумом
            // двойной точности — 4.900000095367432 вместо 4.9.
            rating: $this->float($rating) !== null
                ? round($this->float($rating), 1)
                : $this->float($this->microdata($html, 'ratingValue')),

            // Два разных счётчика: сколько поставили оценку и сколько
            // из них написали текст.
            ratingsCount: $this->int($ratingsCount) ?? $this->int($this->microdata($html, 'ratingCount')),
            reviewsCount: $this->int($reviewsCount) ?? $this->int($this->microdata($html, 'reviewCount')),
        );
    }

    /**
     * Значение schema.org-микроразметки: <meta itemprop="..." content="...">.
     *
     * Атрибуты ищем в обоих порядках — их взаимное расположение не гарантировано.
     */
    private function microdata(string $html, string $property): ?string
    {
        $name = preg_quote($property, '#');

        $patterns = [
            '#<meta[^>]*\bitemprop=["\']'.$name.'["\'][^>]*\bcontent=["\']([^"\']*)["\']#i',
            '#<meta[^>]*\bcontent=["\']([^"\']*)["\'][^>]*\bitemprop=["\']'.$name.'["\']#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function heading(string $html): ?string
    {
        if (preg_match('#<h1[^>]*>(.*?)</h1>#si', $html, $matches) !== 1) {
            return null;
        }

        return $this->string(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
