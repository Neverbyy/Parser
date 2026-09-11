<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Подмена внешних запросов к Яндекс.Картам.
 *
 * Карточка отдаётся из фикстуры, страницы отзывов — очередью ответов, как их
 * отдавал бы настоящий API. Когда очередь заканчивается, приходит пустая
 * страница: именно так Яндекс сообщает, что отзывы кончились.
 *
 * Подмена ставится один раз за тест и читает сценарий из свойств, поэтому
 * повторный вызов переопределяет поведение. Через Http::fake() так сделать
 * нельзя: повторный вызов не заменяет стабы, а добавляется к ним, и
 * срабатывает всё равно первый — уже исчерпанный.
 */
trait FakesYandexMaps
{
    /** @var list<array<string, mixed>> */
    private array $yandexReviewPages = [];

    private string $yandexCardHtml = '';

    private bool $yandexFaked = false;

    /** Нестандартный ответ API: сырое тело или код состояния. */
    private ?Closure $yandexApiResponder = null;

    protected function cardPageHtml(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/card-page.html'));
    }

    /**
     * Карточка организации, у которой отзывов нет вовсе.
     */
    protected function cardPageHtmlWithoutReviews(): string
    {
        return $this->cardPageHtmlWith(0.0, 0, 0);
    }

    /**
     * Карточка с другими агрегатами — чтобы проверить историю изменений.
     */
    protected function cardPageHtmlWith(float $rating, int $ratingsCount, int $reviewsCount): string
    {
        return str_replace(
            [
                '"ratingCount":3081,"ratingValue":4.300000190734863,"reviewCount":1391',
                '<meta itemProp="reviewCount" content="1391"/>',
                '<meta itemProp="ratingCount" content="3081"/>',
                '<meta itemProp="ratingValue" content="4.3"/>',
            ],
            [
                sprintf('"ratingCount":%d,"ratingValue":%s,"reviewCount":%d', $ratingsCount, $rating, $reviewsCount),
                sprintf('<meta itemProp="reviewCount" content="%d"/>', $reviewsCount),
                sprintf('<meta itemProp="ratingCount" content="%d"/>', $ratingsCount),
                sprintf('<meta itemProp="ratingValue" content="%s"/>', $rating),
            ],
            $this->cardPageHtml(),
        );
    }

    /**
     * Реальная по форме страница отзывов из фикстуры: три штуки, включая
     * анонимный отзыв и отзыв с ответом заведения.
     *
     * @return array<string, mixed>
     */
    protected function reviewsFixture(): array
    {
        $json = (string) file_get_contents(base_path('tests/Fixtures/reviews-page.json'));

        return json_decode($json, true);
    }

    /**
     * Синтетическая страница нужного размера — для проверки пагинации.
     *
     * @return array<string, mixed>
     */
    protected function reviewsPage(int $count, int $offset = 0): array
    {
        $reviews = [];

        for ($i = 0; $i < $count; $i++) {
            $index = $offset + $i;

            $reviews[] = [
                'reviewId' => "review-{$index}",
                'author' => ['name' => "Автор {$index}", 'avatarUrl' => null],
                'text' => "Текст отзыва {$index}",
                'rating' => ($index % 5) + 1,
                'updatedTime' => now()->subMinutes($index)->format('Y-m-d\TH:i:s.v\Z'),
            ];
        }

        return ['data' => ['reviews' => $reviews]];
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    protected function fakeYandexMaps(array $pages, ?string $html = null): void
    {
        $this->yandexReviewPages = $pages;
        $this->yandexApiResponder = null;

        $this->installYandexFake($html);
    }

    /** API отвечает не-JSON: сломался формат или прилетела заглушка. */
    protected function fakeYandexMapsRaw(string $body, ?string $html = null): void
    {
        $this->yandexApiResponder = fn () => Http::response($body, 200);

        $this->installYandexFake($html);
    }

    /** API отвечает кодом состояния: 429 — придержали, 403 — заблокировали. */
    protected function fakeYandexMapsStatus(int $status, ?string $html = null): void
    {
        $this->yandexApiResponder = fn () => Http::response('', $status);

        $this->installYandexFake($html);
    }

    private function installYandexFake(?string $html): void
    {
        $this->yandexCardHtml = $html ?? $this->cardPageHtml();

        if ($this->yandexFaked) {
            return;
        }

        $this->yandexFaked = true;

        // Ни один тест не должен случайно уйти в настоящий Яндекс.
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), '/maps/api/business/fetchReviews')) {
                return Http::response($this->yandexCardHtml, 200);
            }

            if ($this->yandexApiResponder !== null) {
                return ($this->yandexApiResponder)();
            }

            $page = array_shift($this->yandexReviewPages) ?? ['data' => ['reviews' => []]];

            return Http::response($page, 200);
        });
    }
}
