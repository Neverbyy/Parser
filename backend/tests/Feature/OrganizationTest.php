<?php

namespace Tests\Feature;

use App\Enums\ParseStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesYandexMaps;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use FakesYandexMaps, RefreshDatabase;

    private const CARD_URL = 'https://yandex.ru/maps/org/mu-mu/1145449555/';

    public function test_guests_cannot_save_a_link(): void
    {
        $this->postJson('/api/organization', ['url' => self::CARD_URL])->assertUnauthorized();
    }

    public function test_it_rejects_a_link_that_is_not_a_yandex_maps_organisation(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => 'https://maps.google.com/place/12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_it_requires_a_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_saving_a_link_parses_the_organisation_and_stores_its_reviews(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/organization', ['url' => self::CARD_URL]);

        $response->assertOk()
            ->assertJsonPath('data.status', ParseStatus::Ready->value)
            ->assertJsonPath('data.yandex_id', '1145449555')
            ->assertJsonPath('data.name', 'Му-Му')
            ->assertJsonPath('data.address', 'ул. Коровий Вал, 1, Москва')
            ->assertJsonPath('data.rating', 4.3)
            ->assertJsonPath('data.fetched_reviews_count', 3)
            ->assertJsonPath('data.error_message', null);

        $this->assertDatabaseCount('reviews', 3);
        $this->assertSame(1, $user->fresh()->organization_id);
    }

    public function test_it_keeps_the_number_of_ratings_and_reviews_apart(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL])
            ->assertOk()
            // 3081 человек поставили оценку, 1391 из них написали текст,
            // а выгрузить удалось только то, что отдал Яндекс.
            ->assertJsonPath('data.ratings_count', 3081)
            ->assertJsonPath('data.reviews_count', 1391)
            ->assertJsonPath('data.fetched_reviews_count', 3);
    }

    public function test_it_stores_author_date_text_and_rating_of_every_review(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL])->assertOk();

        $this->assertDatabaseHas('reviews', [
            'yandex_review_id' => 'review-with-everything',
            'author_name' => 'Кучеров Евгений',
            'rating' => 2,
            'text' => 'Стало очень дорого и многое невкусно.',
        ]);

        // Анонимный отзыв сохраняется без автора, а не теряется.
        $this->assertDatabaseHas('reviews', [
            'yandex_review_id' => 'review-without-author',
            'author_name' => null,
            'rating' => 4,
        ]);
    }

    public function test_it_walks_every_page_until_yandex_returns_an_empty_one(): void
    {
        $this->fakeYandexMaps([
            $this->reviewsPage(50, 0),
            $this->reviewsPage(50, 50),
            $this->reviewsPage(20, 100),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL])
            ->assertOk()
            ->assertJsonPath('data.fetched_reviews_count', 120);

        $this->assertDatabaseCount('reviews', 120);
    }

    public function test_it_ignores_reviews_repeated_across_pages(): void
    {
        // Яндекс иногда отдаёт одну и ту же страницу повторно —
        // дубликаты не должны попадать в базу.
        $this->fakeYandexMaps([
            $this->reviewsPage(50, 0),
            $this->reviewsPage(50, 0),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL])
            ->assertOk()
            ->assertJsonPath('data.fetched_reviews_count', 50);
    }

    public function test_it_records_a_failure_when_the_card_page_is_gone(): void
    {
        Http::preventStrayRequests();
        Http::fake(['yandex.ru/maps/org/*' => Http::response('Not found', 404)]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL]);

        $response->assertOk()
            ->assertJsonPath('data.status', ParseStatus::Failed->value)
            ->assertJsonPath('data.fetched_reviews_count', 0);

        $this->assertStringContainsString('404', (string) $response->json('data.error_message'));
    }

    public function test_it_records_a_failure_when_the_markup_changed(): void
    {
        $this->fakeYandexMaps([], '<html><body><h1>Му-Му</h1></body></html>');

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL]);

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);

        $this->assertStringContainsString('разметк', (string) $response->json('data.error_message'));
    }

    public function test_it_records_a_failure_when_yandex_answers_with_a_captcha(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'yandex.ru/maps/api/business/fetchReviews*' => Http::response([
                'type' => 'captcha',
                'captcha' => ['captcha-page' => 'https://yandex.ru/showcaptcha'],
            ], 200),
            'yandex.ru/maps/org/*' => Http::response($this->cardPageHtml(), 200),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/organization', ['url' => self::CARD_URL]);

        $response->assertOk()->assertJsonPath('data.status', ParseStatus::Failed->value);

        $this->assertStringContainsString('робот', (string) $response->json('data.error_message'));
    }

    public function test_it_returns_null_when_no_link_was_saved_yet(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/organization')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_saving_the_same_organisation_twice_does_not_duplicate_it(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/organization', ['url' => self::CARD_URL])->assertOk();

        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->actingAs($user)->postJson('/api/organization', ['url' => 'https://yandex.ru/maps/org/1145449555/'])->assertOk();

        $this->assertSame(1, Organization::count());
        $this->assertDatabaseCount('reviews', 3);
    }

    public function test_refresh_reparses_the_saved_organisation(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/organization', ['url' => self::CARD_URL])->assertOk();

        $this->fakeYandexMaps([$this->reviewsPage(10)]);

        $this->actingAs($user)->postJson('/api/organization/refresh')
            ->assertOk()
            ->assertJsonPath('data.fetched_reviews_count', 10);

        // Отзывы заменяются целиком: старых в базе остаться не должно.
        $this->assertDatabaseCount('reviews', 10);
        $this->assertDatabaseMissing('reviews', ['yandex_review_id' => 'review-with-everything']);
    }

    public function test_refresh_requires_a_saved_organisation(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organization/refresh')
            ->assertNotFound();
    }
}
