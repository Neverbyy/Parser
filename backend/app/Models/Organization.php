<?php

namespace App\Models;

use App\Enums\ParseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'yandex_id',
    'url',
    'name',
    'address',
    'rating',
    'ratings_count',
    'reviews_count',
    'fetched_reviews_count',
    'status',
    'error_message',
    'parsed_at',
])]
class Organization extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParseStatus::class,
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'fetched_reviews_count' => 'integer',
            'parsed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * История агрегатов: свежие снимки первыми.
     *
     * @return HasMany<OrganizationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class)->latest('created_at');
    }
}
