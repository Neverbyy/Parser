<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Неизменяемый снимок агрегатов организации.
 *
 * Пишется только когда значения действительно изменились, — иначе повторные
 * разборы без новостей засоряли бы историю одинаковыми строками.
 */
#[Fillable([
    'organization_id',
    'rating',
    'ratings_count',
    'reviews_count',
    'fetched_reviews_count',
])]
class OrganizationSnapshot extends Model
{
    /** Снимок не редактируется, поэтому updated_at не нужен. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'fetched_reviews_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Значения, по которым решается, изменилось ли что-нибудь.
     *
     * @return array<string, int|float|null>
     */
    public function values(): array
    {
        return [
            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'fetched_reviews_count' => $this->fetched_reviews_count,
        ];
    }
}
