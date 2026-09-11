<?php

namespace Tests\Unit;

use App\Services\Yandex\Exceptions\InvalidOrganizationUrlException;
use App\Services\Yandex\YandexMapsUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YandexMapsUrlParserTest extends TestCase
{
    private YandexMapsUrlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new YandexMapsUrlParser;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validUrls(): array
    {
        return [
            'со слагом' => ['https://yandex.ru/maps/org/yandeks/1124715036/', '1124715036'],
            'без слага' => ['https://yandex.ru/maps/org/1124715036/', '1124715036'],
            'вкладка отзывов' => ['https://yandex.ru/maps/org/yandeks/1124715036/reviews/', '1124715036'],
            'с query-хвостом' => ['https://yandex.ru/maps/org/yandeks/1124715036/?ll=37.58%2C55.73&z=17', '1124715036'],
            'турецкое зеркало' => ['https://yandex.com.tr/harita/org/1124715036/', '1124715036'],
            'казахское зеркало' => ['https://yandex.kz/maps/org/yandeks/1124715036/', '1124715036'],
            'профиль организации' => ['https://yandex.ru/profile/1124715036', '1124715036'],
            'поисковая выдача с oid' => ['https://yandex.ru/maps/213/moscow/search/?oid=1124715036&ol=biz', '1124715036'],
            'без протокола' => ['yandex.ru/maps/org/yandeks/1124715036/', '1124715036'],
            'с пробелами по краям' => ['   https://yandex.ru/maps/org/1124715036/   ', '1124715036'],
        ];
    }

    #[DataProvider('validUrls')]
    public function test_it_extracts_the_organization_id(string $url, string $expected): void
    {
        $this->assertSame($expected, $this->parser->parse($url)->yandexId);
    }

    public function test_it_normalises_any_link_to_the_reviews_page(): void
    {
        $parsed = $this->parser->parse('https://yandex.com.tr/harita/org/1124715036/');

        $this->assertSame('https://yandex.ru/maps/org/1124715036/reviews/', $parsed->cardUrl);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'пустая строка' => [''],
            'чужой домен' => ['https://maps.google.com/place/12345'],
            'домен, похожий на яндекс' => ['https://yandex.ru.evil.com/maps/org/1124715036/'],
            'главная карт' => ['https://yandex.ru/maps/'],
            'город без организации' => ['https://yandex.ru/maps/213/moscow/'],
            'категория без организации' => ['https://yandex.ru/maps/213/moscow/category/cafe/184106390/'],
            'просто текст' => ['не ссылка вовсе'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_links_that_are_not_organisation_cards(string $url): void
    {
        $this->expectException(InvalidOrganizationUrlException::class);

        $this->parser->parse($url);
    }

    public function test_it_recognises_short_share_links(): void
    {
        $this->assertTrue($this->parser->isShortLink('https://yandex.ru/maps/-/CDxxxxxx'));
        $this->assertFalse($this->parser->isShortLink('https://yandex.ru/maps/org/1124715036/'));
        $this->assertFalse($this->parser->isShortLink('https://example.com/maps/-/CDxxxxxx'));
    }

    public function test_rejected_url_explains_what_is_wrong(): void
    {
        $this->expectExceptionMessage('Ссылка должна вести на Яндекс.Карты.');

        $this->parser->parse('https://maps.google.com/place/12345');
    }
}
