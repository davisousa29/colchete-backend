<?php

namespace App\Services;

use App\Models\AceiteTermo;
use App\Models\Termo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TermosService
{
    /**
     * Versão mais recente de cada tipo — é a que o app e as páginas exibem.
     * Retorna uma coleção indexada pelo tipo: ['uso' => Termo, 'privacidade' => Termo].
     */
    public function vigentes(): Collection
    {
        return collect(array_keys(Termo::TIPOS))
            ->map(fn (string $tipo) => Termo::where('tipo', $tipo)->orderByDesc('versao')->first())
            ->filter()
            ->keyBy('tipo');
    }

    /**
     * Versão mínima que cada pessoa precisa ter aceitado, por tipo:
     * a mais recente publicada que exigiu novo aceite.
     */
    private function versoesMinimas(): Collection
    {
        return Termo::query()
            ->where('exige_novo_aceite', true)
            ->selectRaw('tipo, MAX(versao) AS versao')
            ->groupBy('tipo')
            ->pluck('versao', 'tipo');
    }

    /**
     * Termos vigentes que a pessoa ainda precisa aceitar (vazio = tudo em dia).
     */
    public function pendentes(User $user): Collection
    {
        $aceitas = AceiteTermo::query()
            ->join('termos', 'termos.id', '=', 'aceites_termos.termo_id')
            ->where('aceites_termos.user_id', $user->id)
            ->selectRaw('termos.tipo, MAX(termos.versao) AS versao')
            ->groupBy('termos.tipo')
            ->pluck('versao', 'tipo');

        $vigentes = $this->vigentes();

        return $this->versoesMinimas()
            ->filter(fn ($minima, $tipo) => ($aceitas[$tipo] ?? 0) < $minima)
            ->keys()
            ->map(fn (string $tipo) => $vigentes[$tipo])
            ->values();
    }

    public function possuiPendencias(User $user): bool
    {
        return $this->pendentes($user)->isNotEmpty();
    }

    /**
     * Confere se os ids recebidos são exatamente as versões vigentes de todos os tipos.
     * Evita aceitar uma versão antiga que ficou aberta na tela do app.
     */
    public function saoVigentes(array $termoIds): bool
    {
        $vigentes = $this->vigentes()->pluck('id')->sort()->values()->all();
        $enviados = collect($termoIds)->unique()->sort()->values()->all();

        return $vigentes !== [] && $vigentes === $enviados;
    }

    /**
     * Registra o aceite. Idempotente: aceitar duas vezes a mesma versão não duplica.
     */
    public function registrarAceite(User $user, array $termoIds, ?string $ip, ?string $userAgent): void
    {
        DB::transaction(function () use ($user, $termoIds, $ip, $userAgent) {
            foreach (array_unique($termoIds) as $termoId) {
                AceiteTermo::firstOrCreate(
                    ['user_id' => $user->id, 'termo_id' => $termoId],
                    [
                        'aceito_em'  => now(),
                        'ip'         => $ip,
                        'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
                    ],
                );
            }
        });
    }
}
