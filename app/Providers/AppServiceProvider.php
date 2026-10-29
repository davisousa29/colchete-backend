<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        $this->limitesDeRequisicao();
    }

    /**
     * Limites de tentativas da API.
     *
     * Cada limitador nomeado tem o próprio contador. (Com "throttle:5,2" direto na rota,
     * todas as rotas assim dividiam um contador só por IP.)
     *
     * Chaves:
     * - por IP: rotas públicas sem identificação;
     * - por e-mail + IP: quem erra bloqueia só a tentativa naquela conta, e não todo mundo
     *   que divide a mesma internet (ex.: o Wi-Fi de uma fazenda);
     * - por usuário: rotas que exigem login.
     */
    private function limitesDeRequisicao(): void
    {
        // ── Login e sessão ────────────────────────────────────────────────────
        RateLimiter::for('login', fn (Request $request) => [
            $this->limite(Limit::perMinutes(2, 5)->by('conta:' . $this->emailEIp($request))),
            // Barra quem tenta várias contas a partir do mesmo lugar
            $this->limite(Limit::perMinute(20)->by('ip:' . $request->ip())),
        ]);

        RateLimiter::for('refresh', fn (Request $request) =>
        $this->limite(Limit::perMinute(20)->by($request->ip()))
        );

        RateLimiter::for('login-google', fn (Request $request) =>
        $this->limite(Limit::perMinute(10)->by($request->ip()))
        );

        // ── 2FA no login ──────────────────────────────────────────────────────
        RateLimiter::for('2fa-enviar-email', fn (Request $request) =>
        $this->limite(Limit::perMinutes(2, 5)->by($this->emailEIp($request)))
        );

        RateLimiter::for('2fa-verificar', fn (Request $request) =>
        $this->limite(Limit::perMinutes(2, 10)->by($this->emailEIp($request)))
        );

        // ── Recuperação de senha ──────────────────────────────────────────────
        RateLimiter::for('recuperar-senha', fn (Request $request) =>
        $this->limite(Limit::perMinutes(2, 5)->by($this->emailEIp($request)))
        );

        RateLimiter::for('redefinir-senha', fn (Request $request) =>
        $this->limite(Limit::perMinutes(2, 5)->by($this->emailEIp($request)))
        );

        // ── Cadastro ──────────────────────────────────────────────────────────
        RateLimiter::for('cadastro', fn (Request $request) =>
        $this->limite(Limit::perHour(10)->by($request->ip()))
        );

        RateLimiter::for('cadastro-confirmar', fn (Request $request) =>
        $this->limite(Limit::perMinutes(10, 10)->by($request->ip()))
        );

        RateLimiter::for('cadastro-reenviar', fn (Request $request) =>
        $this->limite(Limit::perMinutes(10, 5)->by($request->ip()))
        );

        // ── Termos ────────────────────────────────────────────────────────────
        RateLimiter::for('termos', fn (Request $request) =>
        $this->limite(Limit::perMinute(60)->by($request->ip()))
        );

        RateLimiter::for('termos-aceitar', fn (Request $request) =>
        $this->limite(Limit::perMinute(10)->by($this->usuarioOuIp($request)))
        );

        // ── Rotas logadas ─────────────────────────────────────────────────────
        RateLimiter::for('checkout', fn (Request $request) =>
        $this->limite(Limit::perMinute(10)->by($this->usuarioOuIp($request)))
        );

        RateLimiter::for('2fa-configurar', fn (Request $request) =>
        $this->limite(Limit::perMinute(10)->by($this->usuarioOuIp($request)))
        );

        RateLimiter::for('exclusao-conta', fn (Request $request) =>
        $this->limite(Limit::perMinutes(10, 5)->by($this->usuarioOuIp($request)))
        );

        RateLimiter::for('alteracao-senha', fn (Request $request) =>
        $this->limite(Limit::perMinutes(10, 5)->by($this->usuarioOuIp($request)))
        );
    }

    /**
     * Resposta 429 em português, com o tempo de espera (o padrão do Laravel é "Too Many Attempts.").
     */
    private function limite(Limit $limit): Limit
    {
        return $limit->response(fn (Request $request, array $headers) => response()->json([
            'message'            => 'Muitas tentativas. Aguarde um pouco e tente novamente.',
            'segundos_restantes' => (int) ($headers['Retry-After'] ?? 60),
        ], 429, $headers));
    }

    private function emailEIp(Request $request): string
    {
        return strtolower(trim((string) $request->input('email'))) . '|' . $request->ip();
    }

    private function usuarioOuIp(Request $request): string
    {
        return (string) ($request->user()?->id ?: $request->ip());
    }
}
