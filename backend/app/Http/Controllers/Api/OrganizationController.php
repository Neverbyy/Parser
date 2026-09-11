<?php

namespace App\Http\Controllers\Api;

use App\Enums\ParseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Services\Yandex\YandexOrganizationScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Страница настроек: сохранение ссылки и запуск разбора карточки.
 *
 * Сам разбор живёт в App\Services\Yandex и запускается через очередь —
 * контроллер только проверяет вход и связывает данные.
 */
class OrganizationController extends Controller
{
    /**
     * Организация, сохранённая текущим пользователем.
     */
    public function show(Request $request): OrganizationResource|JsonResponse
    {
        $organization = $request->user()->organization;

        if ($organization === null) {
            return response()->json(['data' => null]);
        }

        return new OrganizationResource($organization);
    }

    public function store(SaveOrganizationRequest $request, YandexOrganizationScraper $scraper): JsonResponse
    {
        // Идентификатор нужен до парсинга, чтобы не плодить дубли карточек.
        // Для коротких ссылок здесь происходит один переход по редиректу.
        $parsed = $scraper->resolve($request->organizationUrl());

        $organization = Organization::updateOrCreate(
            ['yandex_id' => $parsed->yandexId],
            [
                'url' => $parsed->cardUrl,
                'status' => ParseStatus::Pending,
                'error_message' => null,
            ],
        );

        $request->user()->update(['organization_id' => $organization->id]);

        ParseOrganizationJob::dispatch($organization);

        // При QUEUE_CONNECTION=sync задача уже отработала, и в ответ уходит
        // готовый результат. При реальной очереди здесь будет status=pending,
        // и фронт дождётся готовности, опрашивая GET /api/organization.
        //
        // Статус задаём явно: иначе JsonResource отвечал бы 201 для новой
        // карточки и 200 для уже известной — фронту пришлось бы различать
        // два кода там, где смысл операции всегда один и тот же.
        return OrganizationResource::make($organization->refresh())
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_OK);
    }

    /**
     * Перечитать данные по уже сохранённой ссылке.
     */
    public function refresh(Request $request): OrganizationResource
    {
        $organization = $request->user()->organization;

        abort_if($organization === null, 404, 'Сначала сохраните ссылку на организацию.');

        ParseOrganizationJob::dispatch($organization);

        return new OrganizationResource($organization->refresh());
    }
}
