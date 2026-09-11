<?php

namespace App\Jobs;

use App\Enums\ParseStatus;
use App\Models\Organization;
use App\Models\Review;
use App\Services\Yandex\DTO\ScrapeResult;
use App\Services\Yandex\Exceptions\AccessBlockedException;
use App\Services\Yandex\Exceptions\CaptchaDetectedException;
use App\Services\Yandex\Exceptions\OrganizationUnavailableException;
use App\Services\Yandex\Exceptions\RequestFailedException;
use App\Services\Yandex\Exceptions\YandexScraperException;
use App\Services\Yandex\YandexOrganizationScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Парсинг организации и сохранение результата.
 *
 * Задача оформлена как job, хотя по умолчанию очередь работает в режиме sync:
 * контракт API от этого не зависит, и чтобы вынести разбор в фон, достаточно
 * поменять QUEUE_CONNECTION — трогать код не придётся.
 *
 * Ошибки парсера не выбрасываются наружу, а записываются в саму организацию:
 * у фронта остаётся одно правило на все случаи — смотреть на status.
 */
class ParseOrganizationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Три захода: разовые сбои Яндекса (пятисотка, обрыв, капча) обычно
     * проходят сами, а упорствовать дольше — напрашиваться на блокировку.
     */
    public int $tries = 3;

    /**
     * Один проход — до полуминуты, плюс запас на повторы внутри клиента.
     */
    public int $timeout = 300;

    public function __construct(public readonly Organization $organization) {}

    /**
     * Паузы между попытками растут: сразу ломиться обратно после отказа —
     * худшее, что можно сделать с источником, который только что нас отшил.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Одну организацию одновременно разбирает только одна задача: иначе два
     * параллельных прохода писали бы отзывы в одни и те же строки.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->organization->id))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function handle(YandexOrganizationScraper $scraper): void
    {
        $this->organization->update([
            'status' => ParseStatus::Parsing,
            'error_message' => null,
        ]);

        try {
            $result = $scraper->scrape(
                $this->organization->url,
                // Прогресс виден снаружи ещё до конца разбора: фронт опрашивает
                // организацию, пока статус parsing, и показывает счётчик.
                fn (int $collected) => $this->organization->update([
                    'fetched_reviews_count' => $collected,
                ]),
            );
        } catch (YandexScraperException $exception) {
            $this->handleScraperFailure($exception);

            return;
        } catch (Throwable $exception) {
            // Непредвиденная ошибка — это баг, его нужно видеть.
            $this->markFailed('Внутренняя ошибка при разборе данных организации.');

            throw $exception;
        }

        $this->store($result);
    }

    /**
     * Вызывается очередью, когда попытки исчерпаны или задача упала совсем.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed(
            $exception instanceof YandexScraperException
                ? $exception->getMessage()
                : 'Не удалось разобрать организацию после нескольких попыток.',
        );
    }

    private function handleScraperFailure(YandexScraperException $exception): void
    {
        Log::warning('Не удалось разобрать организацию в Яндекс.Картах', [
            'organization_id' => $this->organization->id,
            'yandex_id' => $this->organization->yandex_id,
            'code' => $exception->errorCode(),
            'attempt' => $this->attempts(),
            'message' => $exception->getMessage(),
        ]);

        if ($this->isTransient($exception) && $this->canRetry()) {
            // Попытки ещё есть: остаёмся в состоянии «идёт разбор», чтобы
            // интерфейс не мигал ошибкой между заходами.
            $this->organization->update([
                'error_message' => "Попытка {$this->attempts()} не удалась: {$exception->getMessage()}",
            ]);

            $this->release($this->nextDelay());

            return;
        }

        $this->markFailed($exception->getMessage());
    }

    /**
     * Временные сбои имеет смысл повторить; неверная ссылка или изменившийся
     * формат ответа от повтора не исправятся.
     */
    private function isTransient(YandexScraperException $exception): bool
    {
        return $exception instanceof RequestFailedException
            || $exception instanceof CaptchaDetectedException
            || $exception instanceof AccessBlockedException
            || $exception instanceof OrganizationUnavailableException;
    }

    /**
     * На sync-драйвере повторов не бывает: задача выполняется прямо в запросе,
     * и release() ничего не перезапустит. Значит, и притворяться не нужно —
     * сразу фиксируем неудачу.
     */
    private function canRetry(): bool
    {
        return $this->job !== null
            && $this->job->getConnectionName() !== 'sync'
            && $this->attempts() < $this->tries;
    }

    private function nextDelay(): int
    {
        $delays = $this->backoff();

        return $delays[min($this->attempts() - 1, count($delays) - 1)];
    }

    private function store(ScrapeResult $result): void
    {
        DB::transaction(function () use ($result): void {
            $this->syncReviews($result);

            $snapshot = $result->organization;

            $this->organization->update([
                'name' => $snapshot->name,
                'address' => $snapshot->address,
                'rating' => $snapshot->rating,
                'ratings_count' => $snapshot->ratingsCount,
                'reviews_count' => $snapshot->reviewsCount,
                'fetched_reviews_count' => count($result->reviews),
                'status' => ParseStatus::Ready,
                'error_message' => null,
                'parsed_at' => now(),
            ]);

            $this->recordSnapshot();
        });
    }

    /**
     * Идемпотентное сохранение: существующие отзывы обновляются по ключу
     * (organization_id, yandex_review_id), новые добавляются, дубликатов не
     * возникает. Строка при этом переживает повторный разбор вместе со своим
     * created_at — видно, когда отзыв появился у нас впервые.
     */
    private function syncReviews(ScrapeResult $result): void
    {
        $now = Carbon::now();

        $rows = array_map(fn ($review): array => [
            'organization_id' => $this->organization->id,
            'yandex_review_id' => $review->id,
            'author_name' => $review->authorName,
            'author_avatar_url' => $review->authorAvatarUrl,
            'rating' => $review->rating,
            'text' => $review->text,
            'published_at' => $review->publishedAt?->toDateTimeString(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $result->reviews);

        foreach (array_chunk($rows, 200) as $chunk) {
            Review::upsert(
                $chunk,
                ['organization_id', 'yandex_review_id'],
                ['author_name', 'author_avatar_url', 'rating', 'text', 'published_at', 'updated_at'],
            );
        }

        // Отзывы, которых Яндекс больше не отдаёт (удалены или вытеснены за
        // пределы шестисот), убираем: таблица должна соответствовать
        // последнему снимку, иначе fetched_reviews_count разойдётся с числом строк.
        $this->organization->reviews()
            ->whereNotIn('yandex_review_id', array_column($rows, 'yandex_review_id'))
            ->delete();
    }

    /**
     * Пишет снимок агрегатов, только если они изменились с прошлого раза —
     * иначе регулярный разбор засорял бы историю одинаковыми строками.
     */
    private function recordSnapshot(): void
    {
        $organization = $this->organization->refresh();

        $values = [
            'rating' => $organization->rating,
            'ratings_count' => $organization->ratings_count,
            'reviews_count' => $organization->reviews_count,
            'fetched_reviews_count' => $organization->fetched_reviews_count,
        ];

        $latest = $organization->snapshots()->first();

        if ($latest !== null && $latest->values() == $values) {
            return;
        }

        $organization->snapshots()->create($values);
    }

    /**
     * Именно markFailed, а не fail(): fail() уже есть в InteractsWithQueue
     * и помечает саму задачу упавшей — переопределять его нельзя.
     */
    private function markFailed(string $message): void
    {
        $this->organization->update([
            'status' => ParseStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
