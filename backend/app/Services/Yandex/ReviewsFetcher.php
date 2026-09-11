<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\CardPage;
use App\Services\Yandex\DTO\ParsedOrganizationUrl;
use App\Services\Yandex\DTO\ReviewData;
use App\Services\Yandex\Exceptions\CaptchaDetectedException;
use App\Services\Yandex\Exceptions\EmptyResponseException;
use App\Services\Yandex\Exceptions\MarkupChangedException;

/**
 * Постраничный обход отзывов через внутренний API карточки.
 *
 * На самой странице отзывы догружаются скриптом по мере прокрутки, но за этой
 * прокруткой стоит обычный GET-запрос, который можно повторить напрямую —
 * поэтому headless-браузер здесь не нужен.
 *
 * Яндекс отдаёт максимум 50 отзывов за запрос и обрезает выдачу на 600 штук
 * (12 страниц) независимо от того, сколько отзывов у организации на самом деле:
 * проверено на карточках с 5858, 1391 и 778 отзывами — во всех трёх случаях
 * тринадцатая страница приходит пустой. Поэтому единственный надёжный признак
 * конца — пустая страница, а не data.params.totalPages: тот рапортует полное
 * число страниц (118 для первой карточки) и до конца досчитать не даёт.
 */
final class ReviewsFetcher
{
    private const ENDPOINT = 'https://yandex.ru/maps/api/business/fetchReviews';

    public function __construct(
        private readonly YandexMapsClient $client,
        private readonly ReviewsRequestSigner $signer,
    ) {}

    /**
     * @param  (callable(int): void)|null  $onProgress  вызывается после каждой
     *                                                  страницы с общим числом
     *                                                  собранных отзывов
     * @return list<ReviewData>
     */
    public function fetch(ParsedOrganizationUrl $url, CardPage $card, ?callable $onProgress = null): array
    {
        $maxPages = max(1, (int) config('yandex.max_pages'));
        $delayMs = max(0, (int) config('yandex.request_delay_ms'));
        $jitterMs = max(0, (int) config('yandex.request_jitter_ms'));

        $csrfToken = $card->csrfToken;
        $reviews = [];
        $seen = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = $this->requestPage($url, $card, $csrfToken, $page);
            $items = data_get($payload, 'data.reviews');

            if (! is_array($items)) {
                // На первой странице это означает, что отвечать нечем.
                // Дальше — просто конец выдачи.
                if ($page === 1) {
                    throw EmptyResponseException::make();
                }

                break;
            }

            if ($items === []) {
                break;
            }

            $added = 0;
            $recognised = 0;

            foreach ($items as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $review = ReviewData::fromApi($raw);

                if ($review === null) {
                    continue;
                }

                $recognised++;

                if (isset($seen[$review->id])) {
                    continue;
                }

                $seen[$review->id] = true;
                $reviews[] = $review;
                $added++;
            }

            // Записи пришли, но ни одна не разобралась в отзыв. Значит,
            // изменились имена полей. Молча вернуть пустоту здесь — худшее,
            // что можно сделать: снаружи это неотличимо от организации,
            // у которой просто нет отзывов.
            if ($recognised === 0) {
                throw MarkupChangedException::unrecognisedReviews();
            }

            // Страница целиком из уже виденных отзывов — выдача зациклилась.
            if ($added === 0) {
                break;
            }

            if ($onProgress !== null) {
                $onProgress(count($reviews));
            }

            if ($page < $maxPages) {
                $this->pause($delayMs, $jitterMs);
            }
        }

        return $reviews;
    }

    /**
     * Пауза между страницами со случайной добавкой: ровный интервал
     * запрос-в-запрос — первое, по чему автоматику отличают от человека.
     */
    private function pause(int $delayMs, int $jitterMs): void
    {
        $total = $delayMs + ($jitterMs > 0 ? random_int(0, $jitterMs) : 0);

        if ($total > 0) {
            usleep($total * 1000);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPage(ParsedOrganizationUrl $url, CardPage $card, string &$csrfToken, int $page): array
    {
        $payload = $this->send($url, $card, $csrfToken, $page);

        // Тот же сценарий отрабатывает клиент Яндекса: если токен протух,
        // сервер возвращает новый прямо в теле, и запрос повторяется один раз.
        $refreshed = $payload['csrfToken'] ?? null;

        if (is_string($refreshed) && $refreshed !== '') {
            $csrfToken = $refreshed;
            $payload = $this->send($url, $card, $csrfToken, $page);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function send(ParsedOrganizationUrl $url, CardPage $card, string $csrfToken, int $page): array
    {
        $params = $this->signer->withSignature([
            'ajax' => '1',
            'businessId' => $url->yandexId,
            'csrfToken' => $csrfToken,
            'locale' => $card->locale,
            'page' => (string) $page,
            'pageSize' => (string) config('yandex.page_size'),
            'ranking' => (string) config('yandex.ranking'),
            'sessionId' => $card->sessionId,
        ]);

        // Собираем строку запроса сами, ровно тем же кодированием, каким
        // считалась подпись, — чтобы отправленное и подписанное совпадали байт в байт.
        $payload = $this->client->fetchJson(
            self::ENDPOINT.'?'.$this->signer->queryString($params),
            $url->cardUrl,
        );

        if (($payload['type'] ?? null) === 'captcha') {
            throw CaptchaDetectedException::make();
        }

        return $payload;
    }
}
