<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'yandex_id' => $this->yandex_id,
            'url' => $this->url,
            'name' => $this->name,
            'address' => $this->address,

            // Средний балл организации.
            'rating' => $this->rating,

            // Два разных числа: сколько всего оценок и сколько из них
            // сопровождаются текстом отзыва.
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,

            // Сколько отзывов реально лежит в базе. Яндекс отдаёт наружу
            // не больше 600, поэтому при крупных организациях это число
            // меньше reviews_count — и это не ошибка.
            'fetched_reviews_count' => $this->fetched_reviews_count,

            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'parsed_at' => $this->parsed_at?->toIso8601String(),
        ];
    }
}
