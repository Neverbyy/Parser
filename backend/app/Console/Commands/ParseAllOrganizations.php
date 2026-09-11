<?php

namespace App\Console\Commands;

use App\Enums\ParseStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Массовый разбор всех сохранённых организаций.
 *
 * Под сценарий «сеть из пятидесяти филиалов»: задачи не выстреливают разом,
 * а раскладываются по времени с постоянным шагом. Пятьдесят карточек — это
 * около семисот запросов к Яндексу, и размазать их на полчаса важнее, чем
 * разобрать всё за минуту и получить блокировку.
 *
 * Ставится в расписание (routes/console.php) или запускается руками.
 */
class ParseAllOrganizations extends Command
{
    protected $signature = 'yandex:parse-all
                            {--gap= : Пауза между организациями в секундах}
                            {--force : Разбирать и те, что уже разбираются}';

    protected $description = 'Поставить в очередь разбор всех сохранённых организаций';

    public function handle(): int
    {
        $gap = (int) ($this->option('gap') ?? config('yandex.organization_gap_seconds'));

        $organizations = Organization::query()
            ->when(
                ! $this->option('force'),
                // Уже идущие разборы не трогаем: WithoutOverlapping их всё равно
                // придержит, но лишние задачи в очереди ни к чему.
                fn ($query) => $query->whereNot('status', ParseStatus::Parsing),
            )
            ->orderBy('id')
            ->get();

        if ($organizations->isEmpty()) {
            $this->warn('Нечего разбирать: сохранённых организаций нет.');

            return self::SUCCESS;
        }

        foreach ($organizations->values() as $index => $organization) {
            $delay = $index * $gap;

            ParseOrganizationJob::dispatch($organization)->delay(now()->addSeconds($delay));

            $this->line(sprintf(
                '  %-40s старт через %s',
                mb_strimwidth((string) ($organization->name ?? $organization->yandex_id), 0, 40, '…'),
                $this->humanDelay($delay),
            ));
        }

        $total = $organizations->count();

        $this->info(sprintf(
            'В очередь поставлено %d организаций, шаг %d с, весь обход займёт около %s.',
            $total,
            $gap,
            $this->humanDelay(($total - 1) * $gap),
        ));

        if (config('queue.default') === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync — задачи выполнились прямо сейчас, без пауз. Для настоящего обхода нужен воркер.');
        }

        return self::SUCCESS;
    }

    private function humanDelay(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} с";
        }

        return sprintf('%d мин %02d с', intdiv($seconds, 60), $seconds % 60);
    }
}
