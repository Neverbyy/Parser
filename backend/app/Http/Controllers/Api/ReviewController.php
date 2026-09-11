<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Постраничная выдача отзывов сохранённой организации.
 *
 * Отзывы отдаются из базы, а не тянутся из Яндекса на каждый запрос:
 * пагинация получается мгновенной, а внешний источник не дёргается лишний раз.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $organization = $request->user()->organization;

        abort_if($organization === null, 404, 'Сначала сохраните ссылку на организацию.');

        $perPage = (int) $request->integer('per_page', self::PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $reviews = $organization->reviews()
            ->orderByDesc('published_at')
            // Отзывы одной секунды должны идти в стабильном порядке,
            // иначе одна и та же запись может попасть на две страницы.
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }
}
