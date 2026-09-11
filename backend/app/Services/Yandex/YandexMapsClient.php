<?php

namespace App\Services\Yandex;

use App\Services\Yandex\Exceptions\AccessBlockedException;
use App\Services\Yandex\Exceptions\EmptyResponseException;
use App\Services\Yandex\Exceptions\OrganizationUnavailableException;
use App\Services\Yandex\Exceptions\RequestFailedException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Транспорт до Яндекс.Карт.
 *
 * Важная деталь: карточка и API отзывов ходят через один и тот же cookie-jar.
 * Яндекс выдаёт на первой же странице набор кук (yandexuid и компанию), и без
 * них внутренний эндпоинт отзывов отвечает 400 даже с корректной подписью.
 * Поэтому клиент живёт ровно один проход парсера и держит куки внутри себя.
 *
 * По той же причине User-Agent и прокси выбираются один раз в конструкторе и
 * дальше не меняются: куки выданы конкретному браузеру с конкретного адреса,
 * и смена того или другого посреди сессии выглядит куда подозрительнее, чем
 * постоянство. Ротация происходит между проходами, а не внутри.
 */
final class YandexMapsClient
{
    private readonly CookieJar $cookies;

    private readonly string $userAgent;

    private readonly ?string $proxy;

    public function __construct()
    {
        $this->cookies = new CookieJar;
        $this->userAgent = $this->pickUserAgent();
        $this->proxy = $this->pickProxy();
    }

    /**
     * Загружает HTML карточки организации.
     */
    public function fetchCardPage(string $url): string
    {
        $response = $this->get($url, [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]);

        $this->guardAgainstBlocking($response);

        if (! $response->successful()) {
            throw OrganizationUnavailableException::status($response->status());
        }

        $html = $response->body();

        if (trim($html) === '') {
            throw EmptyResponseException::make();
        }

        return $html;
    }

    /**
     * Запрос к внутреннему API: ответ ожидается JSON-объектом.
     *
     * @return array<string, mixed>
     */
    public function fetchJson(string $url, string $referer): array
    {
        $response = $this->get($url, [
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => $referer,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->guardAgainstBlocking($response);

        if (! $response->successful()) {
            throw OrganizationUnavailableException::status($response->status());
        }

        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded)) {
            throw EmptyResponseException::make();
        }

        return $decoded;
    }

    /**
     * Разворачивает короткую ссылку «Поделиться» в полный адрес карточки.
     */
    public function resolveShortLink(string $url): string
    {
        $response = $this->send(
            fn (): Response => $this->request()
                ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                ->get($url)
        );

        $history = $response->headers()['X-Guzzle-Redirect-History'] ?? [];
        $last = is_array($history) ? end($history) : false;

        return is_string($last) && $last !== '' ? $last : $url;
    }

    /**
     * 403 и 429 — это не «страница не открылась», а отказ в доступе.
     * Их нужно отличать: лечение у них другое, и повторять запрос сразу
     * бессмысленно.
     */
    private function guardAgainstBlocking(Response $response): void
    {
        if (in_array($response->status(), [403, 429], true)) {
            throw AccessBlockedException::status($response->status());
        }
    }

    private function pickUserAgent(): string
    {
        $pool = config('yandex.user_agents');

        if (is_array($pool) && $pool !== []) {
            return (string) $pool[array_rand($pool)];
        }

        return (string) config('yandex.user_agent');
    }

    private function pickProxy(): ?string
    {
        $proxies = config('yandex.proxies');

        if (! is_array($proxies) || $proxies === []) {
            return null;
        }

        return (string) $proxies[array_rand($proxies)];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function get(string $url, array $headers = []): Response
    {
        return $this->send(fn (): Response => $this->request($headers)->get($url));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(array $headers = []): PendingRequest
    {
        $options = ['cookies' => $this->cookies];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
        }

        return Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept-Language' => (string) config('yandex.accept_language'),
            ...$headers,
        ])
            ->withOptions($options)
            ->timeout((int) config('yandex.timeout'))
            ->connectTimeout((int) config('yandex.connect_timeout'))
            ->retry(
                (int) config('yandex.retries'),
                (int) config('yandex.retry_delay_ms'),
                $this->shouldRetry(...),
                throw: false,
            );
    }

    /**
     * Повторяем только то, что имеет шанс починиться само: обрывы связи
     * и пятисотки. 429 сюда намеренно не входит — на «слишком часто»
     * правильный ответ не «спросить ещё раз через полсекунды», а отступить
     * и дать разобраться вызывающему коду.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $response = $exception instanceof RequestException
            ? $exception->response
            : null;

        return $response !== null
            && in_array($response->status(), [500, 502, 503, 504], true);
    }

    /**
     * @param  callable(): Response  $send
     */
    private function send(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException $exception) {
            throw RequestFailedException::from($exception);
        }
    }
}
