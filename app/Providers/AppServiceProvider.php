<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Payment\PaymentGateway::class,
            \App\Services\Payment\AsaasGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ritmo de envio dos e-mails da fila (ver services.mail_queue)
        RateLimiter::for('emails', fn () => Limit::perSecond(
            config('services.mail_queue.limit'),
            config('services.mail_queue.seconds'),
        ));

        RateLimiter::for('cadastro', fn (Request $request) =>
        Limit::perHour(10)->by($request->ip())
        );

        RateLimiter::for('cadastro-confirmar', fn (Request $request) =>
        Limit::perMinutes(10, 10)->by($request->ip())
        );

        RateLimiter::for('cadastro-reenviar', fn (Request $request) =>
        Limit::perMinutes(10, 5)->by($request->ip())
        );

        RateLimiter::for('exclusao-conta', fn (Request $request) =>
        Limit::perMinutes(10, 5)->by($request->user()?->id ?: $request->ip())
        );
    }
}
