<?php

namespace App\Providers;

use App\Services\Yandex\YandexMapsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Клиент держит cookie-jar, общий для загрузки карточки и последующих
        // запросов к API отзывов: без кук с карточки API отвечает 400. scoped,
        // а не singleton, — чтобы между задачами в очереди сессия не переиспользовалась.
        $this->app->scoped(YandexMapsClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
