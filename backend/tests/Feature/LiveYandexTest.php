<?php

namespace Tests\Feature;

use App\Enums\ParseStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Сквозная проверка против настоящих Яндекс.Карт.
 *
 * Из обычного прогона исключена (см. phpunit.xml): она ходит в сеть, идёт
 * около десяти секунд и зависит от того, что Яндекс сегодня отвечает. Нужна,
 * чтобы убедиться, что парсер всё ещё живой:
 *
 *     php artisan test --group=live
 *
 * Если этот тест упал, а остальные зелёные — сломался не код, а совместимость
 * с внешним источником: сменилась подпись запроса, структура состояния страницы
 * или включилась защита от ботов.
 */
#[Group('live')]
class LiveYandexTest extends TestCase
{
    use RefreshDatabase;

    private const REAL_ORGANIZATION = 'https://yandex.ru/maps/org/mu-mu/1145449555/';

    public function test_it_parses_a_real_organisation_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/organization', ['url' => self::REAL_ORGANIZATION]);

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Ready->value);

        $data = $response->json('data');

        $this->assertSame('1145449555', $data['yandex_id']);
        $this->assertNotEmpty($data['name']);

        // Средний рейтинг в разумных границах.
        $this->assertGreaterThan(0, $data['rating']);
        $this->assertLessThanOrEqual(5, $data['rating']);

        // Оценок всегда больше, чем текстовых отзывов.
        $this->assertGreaterThan($data['reviews_count'], $data['ratings_count']);

        // Яндекс отдаёт наружу не больше 600 отзывов.
        $this->assertGreaterThan(0, $data['fetched_reviews_count']);
        $this->assertLessThanOrEqual(600, $data['fetched_reviews_count']);
    }

    public function test_reviews_of_a_real_organisation_paginate_by_fifty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organization', ['url' => self::REAL_ORGANIZATION])
            ->assertOk();

        $response = $this->actingAs($user)->getJson('/api/organization/reviews');

        $response->assertOk()->assertJsonPath('meta.per_page', 50);

        $first = $response->json('data.0');

        // Каждый отзыв: автор, дата, текст, оценка.
        $this->assertArrayHasKey('author_name', $first);
        $this->assertNotNull($first['published_at']);
        $this->assertNotNull($first['rating']);
    }
}
