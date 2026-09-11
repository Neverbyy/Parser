<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| SPA работает по сессионной куке Sanctum. Перед первым POST-запросом фронту
| нужно получить CSRF-куку: GET /sanctum/csrf-cookie.
|
*/

// Логин ограничен по частоте: шесть попыток в минуту с адреса.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Страница настроек.
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::post('/organization/refresh', [OrganizationController::class, 'refresh']);

    // Отзывы сохранённой организации, по 50 на страницу.
    Route::get('/organization/reviews', [ReviewController::class, 'index']);
});
