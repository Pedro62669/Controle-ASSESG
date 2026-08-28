<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CashFlowReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CashFlowReportService::class);
    }

    public function boot(): void
    {
        // Datas em português nos rótulos dos gráficos e das listagens.
        Carbon::setLocale(config('app.locale'));

        // Em desenvolvimento, N+1 e atributo ausente falham alto em vez de
        // passarem silenciosamente para a tela.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Vite::prefetch(concurrency: 3);
    }
}
