<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\CardPage;
use App\Services\Yandex\DTO\ParsedOrganizationUrl;
use App\Services\Yandex\DTO\ReviewData;
use App\Services\Yandex\DTO\ScrapeResult;
use App\Services\Yandex\Exceptions\MarkupChangedException;

/**
 * Точка входа в парсер: ссылка на входе — данные организации и отзывы на выходе.
 *
 * Порядок шагов важен: сначала загружается карточка, и уже из неё берутся
 * и агрегаты, и ключи доступа к API отзывов (csrfToken, sessionId), и куки —
 * всё это привязано к одной сессии, поэтому клиент между шагами общий.
 */
final class YandexOrganizationScraper
{
    public function __construct(
        private readonly YandexMapsUrlParser $urlParser,
        private readonly YandexMapsClient $client,
        private readonly CardPageParser $cardPageParser,
        private readonly ReviewsFetcher $reviewsFetcher,
    ) {}

    /**
     * @param  (callable(int): void)|null  $onProgress  сколько отзывов собрано
     *                                                  к текущему моменту
     */
    public function scrape(string $url, ?callable $onProgress = null): ScrapeResult
    {
        $parsed = $this->resolve($url);

        $html = $this->client->fetchCardPage($parsed->cardUrl);
        $card = $this->cardPageParser->parse($html, $parsed);
        $reviews = $this->reviewsFetcher->fetch($parsed, $card, $onProgress);

        $this->guardAgainstSilentEmptiness($card, $reviews);

        return new ScrapeResult(
            organization: $card->organization,
            reviews: $reviews,
        );
    }

    /**
     * Сверяет два независимых источника: счётчик с карточки и то, что реально
     * удалось выгрузить.
     *
     * Каждый шаг по отдельности мог отработать без ошибок, но если карточка
     * говорит о полутора тысячах отзывов, а собрано ноль — сломался обход,
     * и сообщить об этом надо явно, а не отдать пустой список как результат.
     *
     * @param  list<ReviewData>  $reviews
     */
    private function guardAgainstSilentEmptiness(CardPage $card, array $reviews): void
    {
        $expected = $card->organization->reviewsCount;

        if ($reviews === [] && $expected !== null && $expected > 0) {
            throw MarkupChangedException::noReviewsDespiteCounter($expected);
        }
    }

    /**
     * Приводит любую ссылку к каноническому виду и достаёт идентификатор.
     *
     * Вызывается до запуска парсинга: идентификатор нужен, чтобы найти или
     * завести организацию в базе. Сетевой запрос здесь бывает ровно один и
     * только для коротких ссылок.
     */
    public function resolve(string $url): ParsedOrganizationUrl
    {
        // Короткая ссылка «Поделиться» не содержит идентификатора —
        // достаём его из адреса, на который она редиректит.
        if ($this->urlParser->isShortLink($url)) {
            $url = $this->client->resolveShortLink($url);
        }

        return $this->urlParser->parse($url);
    }
}
