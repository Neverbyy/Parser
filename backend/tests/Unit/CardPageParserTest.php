<?php

namespace Tests\Unit;

use App\Services\Yandex\CardPageParser;
use App\Services\Yandex\DTO\ParsedOrganizationUrl;
use App\Services\Yandex\Exceptions\MarkupChangedException;
use App\Services\Yandex\Exceptions\OrganizationUnavailableException;
use Tests\Support\FakesYandexMaps;
use Tests\TestCase;

class CardPageParserTest extends TestCase
{
    use FakesYandexMaps;

    private CardPageParser $parser;

    private ParsedOrganizationUrl $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CardPageParser;
        $this->url = new ParsedOrganizationUrl(
            yandexId: '1145449555',
            originalUrl: 'https://yandex.ru/maps/org/mu-mu/1145449555/',
            cardUrl: 'https://yandex.ru/maps/org/1145449555/reviews/',
        );
    }

    public function test_it_reads_aggregates_and_api_credentials_from_the_page(): void
    {
        $card = $this->parser->parse($this->cardPageHtml(), $this->url);

        $this->assertSame('995376acd3fa959b15e031c5127053b537e96e90:1789151481', $card->csrfToken);
        $this->assertSame('ru_RU', $card->locale);
        $this->assertNotSame('', $card->sessionId);

        $organization = $card->organization;
        $this->assertSame('Му-Му', $organization->name);
        $this->assertSame('ул. Коровий Вал, 1, Москва', $organization->address);
        $this->assertSame('1145449555', $organization->yandexId);
    }

    public function test_it_rounds_away_the_floating_point_noise_in_the_rating(): void
    {
        // В состоянии лежит 4.300000190734863 — пользователю нужно 4.3.
        $card = $this->parser->parse($this->cardPageHtml(), $this->url);

        $this->assertSame(4.3, $card->organization->rating);
    }

    public function test_it_keeps_ratings_and_reviews_as_separate_counters(): void
    {
        $organization = $this->parser->parse($this->cardPageHtml(), $this->url)->organization;

        $this->assertSame(3081, $organization->ratingsCount);
        $this->assertSame(1391, $organization->reviewsCount);
    }

    public function test_it_falls_back_to_schema_org_markup_when_the_state_has_no_rating(): void
    {
        // Страховка на случай, если Яндекс поменяет структуру состояния:
        // те же числа продублированы микроразметкой.
        $html = $this->htmlWithoutRatingData();

        $organization = $this->parser->parse($html, $this->url)->organization;

        $this->assertSame(4.3, $organization->rating);
        $this->assertSame(3081, $organization->ratingsCount);
        $this->assertSame(1391, $organization->reviewsCount);
    }

    public function test_it_reports_changed_markup_when_the_state_block_is_gone(): void
    {
        $this->expectException(MarkupChangedException::class);

        $this->parser->parse('<html><body><h1>Му-Му</h1></body></html>', $this->url);
    }

    public function test_it_reports_changed_markup_when_the_state_is_not_valid_json(): void
    {
        $html = '<script type="application/json" class="state-view">{not json</script>';

        $this->expectException(MarkupChangedException::class);

        $this->parser->parse($html, $this->url);
    }

    public function test_it_reports_changed_markup_when_the_csrf_token_disappears(): void
    {
        $html = $this->pageWithState(['config' => ['locale' => 'ru_RU']]);

        $this->expectException(MarkupChangedException::class);

        $this->parser->parse($html, $this->url);
    }

    public function test_it_reports_an_unavailable_organisation_when_the_page_holds_no_business_card(): void
    {
        // Ссылка вела на что-то другое — на город, категорию или удалённую карточку.
        $html = $this->pageWithState([
            'config' => [
                'csrfToken' => 'token:1',
                'locale' => 'ru_RU',
                'counters' => ['analytics' => ['sessionId' => 'session']],
            ],
            'stack' => [['results' => ['items' => [['type' => 'toponym']]]]],
        ]);

        $this->expectException(OrganizationUnavailableException::class);

        $this->parser->parse($html, $this->url);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function pageWithState(array $state, string $body = ''): string
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<html><body>'.$body
            .'<script type="application/json" class="state-view">'.$json.'</script>'
            .'</body></html>';
    }

    private function htmlWithoutRatingData(): string
    {
        $state = [
            'config' => [
                'csrfToken' => 'token:1',
                'locale' => 'ru_RU',
                'counters' => ['analytics' => ['sessionId' => 'session']],
            ],
            'stack' => [[
                'results' => ['items' => [[
                    'type' => 'business',
                    'id' => '1145449555',
                    'title' => 'Му-Му',
                    'address' => 'ул. Коровий Вал, 1, Москва',
                ]]],
            ]],
        ];

        $markup = '<meta itemProp="reviewCount" content="1391"/>'
            .'<meta itemProp="ratingCount" content="3081"/>'
            .'<meta itemProp="ratingValue" content="4.3"/>';

        return $this->pageWithState($state, $markup);
    }
}
