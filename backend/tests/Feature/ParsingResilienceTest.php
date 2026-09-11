<?php

namespace Tests\Feature;

use App\Enums\ParseStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesYandexMaps;
use Tests\TestCase;

/**
 * Парсер не должен «молча» отдавать пустоту, когда источник изменился.
 *
 * Это самый коварный класс поломок: запросы проходят, ошибок нет, статус
 * ready — а отзывов ноль. Снаружи такое неотличимо от организации, у которой
 * отзывов действительно нет.
 */
class ParsingResilienceTest extends TestCase
{
    use FakesYandexMaps, RefreshDatabase;

    private const CARD_URL = 'https://yandex.ru/maps/org/mu-mu/1145449555/';

    private function save(): TestResponse
    {
        return $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL]);
    }

    public function test_it_reports_a_failure_when_review_fields_were_renamed(): void
    {
        // Яндекс отдал записи, но `reviewId` теперь называется иначе.
        $this->fakeYandexMaps([
            ['data' => ['reviews' => [
                ['id' => 'x1', 'author' => ['name' => 'Автор'], 'text' => 'Текст', 'rating' => 5],
                ['id' => 'x2', 'author' => ['name' => 'Автор'], 'text' => 'Текст', 'rating' => 4],
            ]]],
        ]);

        $response = $this->save();

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);
        $this->assertStringContainsString('неизвестном формате', (string) $response->json('data.error_message'));
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_it_reports_a_failure_when_the_card_promises_reviews_but_none_arrive(): void
    {
        // Карточка сообщает о 1391 отзыве, а API отдал пустую первую страницу.
        // Каждый шаг по отдельности «успешен», но вместе это поломка обхода.
        $this->fakeYandexMaps([['data' => ['reviews' => []]]]);

        $response = $this->save();

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);
        $this->assertStringContainsString('1391', (string) $response->json('data.error_message'));
    }

    public function test_it_reports_a_failure_when_the_api_answer_is_not_json(): void
    {
        $this->fakeYandexMapsRaw('<html>Мы вас не ждали</html>');

        $response = $this->save();

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);
        $this->assertNotEmpty($response->json('data.error_message'));
    }

    public function test_it_distinguishes_a_block_from_an_ordinary_error(): void
    {
        $this->fakeYandexMapsStatus(429);

        $response = $this->save();

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);
        $this->assertStringContainsString('частоту запросов', (string) $response->json('data.error_message'));
    }

    public function test_an_organisation_without_reviews_is_not_treated_as_a_failure(): void
    {
        // Честный случай: отзывов нет и счётчик это подтверждает.
        // Проверка на «молчаливую пустоту» не должна его ломать.
        $this->fakeYandexMaps([['data' => ['reviews' => []]]], $this->cardPageHtmlWithoutReviews());

        $this->save()->assertOk()->assertJsonPath('data.status', ParseStatus::Ready->value);
    }
}
