<?php

namespace App\Services\Yandex;

/**
 * Подпись запросов к внутреннему API Яндекс.Карт.
 *
 * Каждый запрос к /maps/api/* фронт Яндекса сопровождает параметром `s`.
 * Без него эндпоинт отвечает 400 с пустым телом. Алгоритм восстановлен из
 * клиентского бандла карт (chunks/base/*.js) и состоит из двух шагов:
 *
 *   1. Параметры сериализуются в query-строку, где ключи отсортированы
 *      по алфавиту без учёта регистра, а ключ и значение закодированы
 *      по RFC 3986 (в PHP это ровно rawurlencode).
 *   2. От получившейся строки считается хеш djb2-xor в 32-битной
 *      беззнаковой арифметике, и его десятичная запись уходит как `s`.
 *
 * Оригинал на JS:
 *
 *   var t = qs.stringify(e, { sort: caseInsensitiveCompare });
 *   for (var r = 0, n = 5381; r < t.length; r++) n = 33 * n ^ t.charCodeAt(r);
 *   return String(n >>> 0);
 *
 * Строка всегда ASCII (после процентного кодирования), поэтому побайтовый
 * ord() здесь эквивалентен charCodeAt() над UTF-16.
 */
final class ReviewsRequestSigner
{
    /**
     * Дописывает к параметрам их подпись.
     *
     * @param  array<string, string|int>  $params
     * @return array<string, string|int>
     */
    public function withSignature(array $params): array
    {
        return [...$params, 's' => $this->sign($params)];
    }

    /**
     * @param  array<string, string|int>  $params
     */
    public function sign(array $params): string
    {
        return (string) $this->hash($this->queryString($params));
    }

    /**
     * Сериализация в том же виде, в каком её делает qs.stringify на фронте.
     *
     * @param  array<string, string|int>  $params
     */
    public function queryString(array $params): string
    {
        $keys = array_map(strval(...), array_keys($params));

        usort($keys, static fn (string $a, string $b): int => strcmp(strtolower($a), strtolower($b)));

        $pairs = array_map(
            static fn (string $key): string => rawurlencode($key).'='.rawurlencode((string) $params[$key]),
            $keys,
        );

        return implode('&', $pairs);
    }

    /**
     * djb2-xor, приведённый к семантике JS: умножение и xor в 32 битах,
     * результат — беззнаковый (аналог `>>> 0`).
     */
    private function hash(string $value): int
    {
        $hash = 5381;

        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $hash = (33 * $hash) & 0xFFFFFFFF;
            $hash = ($hash ^ ord($value[$i])) & 0xFFFFFFFF;
        }

        return $hash;
    }
}
