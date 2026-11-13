<?php

namespace App\Services\Racao;

use App\Models\ProgramaRacao;

/**
 * Números derivados de uma formulação salva, para o relatório (PDF).
 * Só soma e divide valores já gravados pela CalculadoraFormulacao: não recalcula nutrientes.
 */
final class IndicadoresFormulacao
{
    /** Faixa considerada "atendida" no balanço: 95% a 115% do exigido (igual ao app). */
    private const MINIMO_OK = 95;
    private const MAXIMO_OK = 115;

    public function calcular(ProgramaRacao $programa): array
    {
        $ingredientes = $programa->ingredientes;
        $animais      = max(1, (int) $programa->quantidade_animais);

        $pesoInicial = (float) $programa->peso_inicial_kg;
        $pesoFinal   = (float) $programa->peso_final_kg;
        $gmd         = (float) $programa->gmd_kg;
        $cms         = (float) $programa->exig_cms_kg;

        return [
            // Linha de totais da tabela "Composição da dieta" (por animal/dia)
            'totais' => [
                'proporcao_pct'    => round($ingredientes->sum(fn ($i) => (float) $i->proporcao_pct), 2),
                'consumo_ms_kg'    => round($ingredientes->sum(fn ($i) => (float) $i->consumo_ms_kg), 3),
                'consumo_mn_kg'    => round($ingredientes->sum(fn ($i) => (float) $i->consumo_mn_kg), 3),
                'custo_animal_dia' => (float) $programa->custo_animal_dia,
            ],

            // Concentrado × volumoso, por dia no lote (volumoso = principal + suplementar)
            'grupos' => [
                'concentrado' => $this->grupo($ingredientes->where('tipo', 'concentrado'), $animais),
                'volumoso'    => $this->grupo($ingredientes->where('tipo', '!=', 'concentrado'), $animais),
            ],

            // Dias até o peso final: arredonda para cima (o último dia incompleto conta)
            'dias_ate_peso' => $gmd > 0 ? (int) ceil(($pesoFinal - $pesoInicial) / $gmd) : null,

            // kg de MS consumidos para ganhar 1 kg de peso vivo (menor = mais eficiente)
            'conversao_alimentar' => $gmd > 0 && $cms > 0 ? round($cms / $gmd, 2) : null,

            'balanco' => $this->balanco($programa),
        ];
    }

    private function grupo($itens, int $animais): array
    {
        return [
            'mn_kg' => round($itens->sum(fn ($i) => (float) $i->consumo_mn_kg) * $animais, 1),
            'ms_kg' => round($itens->sum(fn ($i) => (float) $i->consumo_ms_kg) * $animais, 1),
            'pct'   => round($itens->sum(fn ($i) => (float) $i->proporcao_pct), 1),
        ];
    }

    /** Fornecido × exigido. O NDT só entra se o exigido foi gravado (formulações antigas não têm). */
    private function balanco(ProgramaRacao $programa): array
    {
        $ing = $programa->ingredientes;

        $linhas = [
            ['label' => 'Nutrientes Digestíveis Totais (NDT)', 'forn' => $ing->sum('contrib_ndt_kg'),   'exig' => $programa->exig_ndt_kg,   'unidade' => 'kg'],
            ['label' => 'Energia Líquida Mantença',            'forn' => $ing->sum('contrib_elm_mcal'), 'exig' => $programa->exig_elm_mcal, 'unidade' => 'Mcal'],
            ['label' => 'Proteína Bruta',                      'forn' => $ing->sum('contrib_pb_g'),     'exig' => $programa->exig_pb_g,     'unidade' => 'g'],
            ['label' => 'Cálcio',                              'forn' => $ing->sum('contrib_ca_g'),     'exig' => $programa->exig_ca_g,     'unidade' => 'g'],
            ['label' => 'Fósforo',                             'forn' => $ing->sum('contrib_p_g'),      'exig' => $programa->exig_p_g,      'unidade' => 'g'],
        ];

        return collect($linhas)
            ->filter(fn ($l) => (float) $l['exig'] > 0)
            ->map(function ($l) {
                $forn = (float) $l['forn'];
                $exig = (float) $l['exig'];
                $pct  = $forn / $exig * 100;

                $status = match (true) {
                    $pct < self::MINIMO_OK => 'baixo',
                    $pct > self::MAXIMO_OK => 'alto',
                    default                => 'ok',
                };

                return [
                    'label'     => $l['label'],
                    'unidade'   => $l['unidade'],
                    'fornecido' => $forn,
                    'exigido'   => $exig,
                    'barra_pct' => min($pct, 100),
                    'status'    => $status,
                ];
            })
            ->values()
            ->all();
    }
}
