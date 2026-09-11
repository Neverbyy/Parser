<?php

namespace App\Rules;

use App\Services\Yandex\Exceptions\InvalidOrganizationUrlException;
use App\Services\Yandex\YandexMapsUrlParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Проверяет, что в поле лежит ссылка на карточку организации в Яндекс.Картах.
 *
 * Правило работает без сети: оно только разбирает адрес. Доступность самой
 * карточки выясняется уже при парсинге и попадает в status организации.
 */
class YandexOrganizationUrl implements ValidationRule
{
    public function __construct(
        private readonly YandexMapsUrlParser $parser = new YandexMapsUrlParser,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Ссылка должна быть строкой.');

            return;
        }

        // У короткой ссылки «Поделиться» идентификатор появляется только
        // после редиректа, поэтому здесь проверяем лишь её формат.
        if ($this->parser->isShortLink($value)) {
            return;
        }

        try {
            $this->parser->parse($value);
        } catch (InvalidOrganizationUrlException $exception) {
            $fail($exception->getMessage());
        }
    }
}
