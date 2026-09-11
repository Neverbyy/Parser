<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesYandexMaps;
use Tests\TestCase;

/**
 * Идемпотентность повторного разбора и история изменений.
 */
class ParsingHistoryTest extends TestCase
{
    use FakesYandexMaps, RefreshDatabase;

    private const CARD_URL = 'https://yandex.ru/maps/org/mu-mu/1145449555/';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function parse(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/organization', ['url' => self::CARD_URL])
            ->assertOk();
    }

    public function test_reparsing_does_not_duplicate_reviews(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $this->assertSame(1, Organization::count());
        $this->assertDatabaseCount('reviews', 3);
    }

    public function test_surviving_reviews_keep_their_row_and_creation_time(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $before = Review::where('yandex_review_id', 'review-with-everything')->firstOrFail();

        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $after = Review::where('yandex_review_id', 'review-with-everything')->firstOrFail();

        // Запись обновилась, а не была удалена и создана заново: видно,
        // когда отзыв появился у нас впервые.
        $this->assertSame($before->id, $after->id);
        $this->assertSame(
            $before->created_at?->toDateTimeString(),
            $after->created_at?->toDateTimeString(),
        );
    }

    public function test_changed_review_text_is_updated_in_place(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $edited = $this->reviewsFixture();
        $edited['data']['reviews'][0]['text'] = 'Автор передумал и переписал отзыв.';
        $edited['data']['reviews'][0]['rating'] = 5;

        $this->fakeYandexMaps([$edited]);
        $this->parse();

        $this->assertDatabaseHas('reviews', [
            'yandex_review_id' => 'review-with-everything',
            'text' => 'Автор передумал и переписал отзыв.',
            'rating' => 5,
        ]);
        $this->assertDatabaseCount('reviews', 3);
    }

    public function test_reviews_yandex_no_longer_returns_are_removed(): void
    {
        $this->fakeYandexMaps([$this->reviewsPage(5)]);
        $this->parse();
        $this->assertDatabaseCount('reviews', 5);

        $this->fakeYandexMaps([$this->reviewsPage(2)]);
        $this->parse();

        // Таблица соответствует последнему снимку, иначе
        // fetched_reviews_count разошёлся бы с числом строк.
        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_the_first_parse_records_a_snapshot(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $snapshot = OrganizationSnapshot::firstOrFail();

        $this->assertSame(4.3, $snapshot->rating);
        $this->assertSame(3081, $snapshot->ratings_count);
        $this->assertSame(1391, $snapshot->reviews_count);
    }

    public function test_a_parse_without_changes_does_not_add_a_snapshot(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        // Регулярный разбор не должен засорять историю одинаковыми строками.
        $this->assertSame(1, OrganizationSnapshot::count());
    }

    public function test_changed_aggregates_are_recorded_as_a_new_snapshot(): void
    {
        $this->fakeYandexMaps([$this->reviewsFixture()]);
        $this->parse();

        $this->fakeYandexMaps([$this->reviewsFixture()], $this->cardPageHtmlWith(4.7, 3500, 1500));
        $this->parse();

        $this->assertSame(2, OrganizationSnapshot::count());

        [$latest, $previous] = OrganizationSnapshot::orderByDesc('id')->get()->all();

        // Стало → было: ровно то, ради чего история и заводилась.
        $this->assertSame(4.7, $latest->rating);
        $this->assertSame(4.3, $previous->rating);
        $this->assertSame(1500, $latest->reviews_count);
        $this->assertSame(1391, $previous->reviews_count);
    }
}
