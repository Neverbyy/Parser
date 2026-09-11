<?php

namespace App\Console\Commands;

use App\Services\Yandex\Exceptions\YandexScraperException;
use App\Services\Yandex\YandexOrganizationScraper;
use Illuminate\Console\Command;

/**
 * Ручная проверка парсера без фронта и без базы.
 *
 * Удобно, когда нужно быстро понять, жив ли разбор карточки: команда ходит
 * в Яндекс по-настоящему и печатает то, что удалось достать.
 */
class ParseYandexOrganization extends Command
{
    protected $signature = 'yandex:parse {url : Ссылка на карточку организации в Яндекс.Картах}';

    protected $description = 'Разобрать карточку организации в Яндекс.Картах и показать результат';

    public function handle(YandexOrganizationScraper $scraper): int
    {
        $url = (string) $this->argument('url');

        $this->info("Разбираю {$url}");
        $startedAt = microtime(true);

        try {
            $result = $scraper->scrape($url);
        } catch (YandexScraperException $exception) {
            $this->error($exception->getMessage());
            $this->line("Код ошибки: {$exception->errorCode()}");

            return self::FAILURE;
        }

        $organization = $result->organization;

        $this->newLine();
        $this->table(['Поле', 'Значение'], [
            ['Название', $organization->name ?? '—'],
            ['Адрес', $organization->address ?? '—'],
            ['ID в Яндексе', $organization->yandexId],
            ['Средний рейтинг', $organization->rating ?? '—'],
            ['Оценок', $organization->ratingsCount ?? '—'],
            ['Отзывов', $organization->reviewsCount ?? '—'],
            ['Выгружено отзывов', count($result->reviews)],
            ['Затрачено', sprintf('%.1f с', microtime(true) - $startedAt)],
        ]);

        if ($result->reviews !== []) {
            $newest = $result->reviews[0];

            $this->newLine();
            $this->line('<comment>Самый свежий отзыв:</comment>');
            $this->line(sprintf(
                '  %s, оценка %s, %s',
                $newest->authorName ?? 'Аноним',
                $newest->rating ?? '—',
                $newest->publishedAt?->format('d.m.Y') ?? '—',
            ));
            $this->line('  '.mb_substr((string) $newest->text, 0, 160));
        }

        return self::SUCCESS;
    }
}
