<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesYandexMaps;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use FakesYandexMaps, RefreshDatabase;

    private const CARD_URL = 'https://yandex.ru/maps/org/mu-mu/1145449555/';

    public function test_guests_cannot_read_reviews(): void
    {
        $this->getJson('/api/organization/reviews')->assertUnauthorized();
    }

    public function test_it_asks_to_save_a_link_first(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/organization/reviews')
            ->assertNotFound();
    }

    public function test_it_returns_fifty_reviews_per_page_by_default(): void
    {
        $user = $this->userWithReviews(120);

        $response = $this->actingAs($user)->getJson('/api/organization/reviews');

        $response->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 120)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_the_last_page_holds_the_remainder(): void
    {
        $user = $this->userWithReviews(120);

        $this->actingAs($user)->getJson('/api/organization/reviews?page=3')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_every_review_carries_author_date_text_and_rating(): void
    {
        $user = $this->userWithReviews(3);

        $review = $this->actingAs($user)->getJson('/api/organization/reviews')->json('data.0');

        $this->assertSame('Автор 0', $review['author_name']);
        $this->assertSame('Текст отзыва 0', $review['text']);
        $this->assertSame(1, $review['rating']);
        $this->assertNotNull($review['published_at']);
    }

    public function test_reviews_are_ordered_from_newest_to_oldest(): void
    {
        $user = $this->userWithReviews(5);

        $dates = collect($this->actingAs($user)->getJson('/api/organization/reviews')->json('data'))
            ->pluck('published_at')
            ->all();

        $sorted = $dates;
        rsort($sorted);

        $this->assertSame($sorted, $dates);
    }

    public function test_pages_do_not_overlap(): void
    {
        $user = $this->userWithReviews(120);

        $first = collect($this->actingAs($user)->getJson('/api/organization/reviews?page=1')->json('data'))->pluck('id');
        $second = collect($this->actingAs($user)->getJson('/api/organization/reviews?page=2')->json('data'))->pluck('id');

        $this->assertEmpty($first->intersect($second));
    }

    public function test_page_size_can_be_narrowed_and_is_capped(): void
    {
        $user = $this->userWithReviews(120);

        $this->actingAs($user)->getJson('/api/organization/reviews?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        // Больше сотни за раз не отдаём, чтобы не выгружать всё одним запросом.
        $this->actingAs($user)->getJson('/api/organization/reviews?per_page=500')
            ->assertOk()
            ->assertJsonCount(100, 'data');
    }

    private function userWithReviews(int $count): User
    {
        $pages = [];

        for ($offset = 0; $offset < $count; $offset += 50) {
            $pages[] = $this->reviewsPage(min(50, $count - $offset), $offset);
        }

        $this->fakeYandexMaps($pages);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/organization', ['url' => self::CARD_URL])->assertOk();

        return $user;
    }
}
