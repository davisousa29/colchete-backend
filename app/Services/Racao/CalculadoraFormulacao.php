<?php

namespace App\Services\Racao;

use App\Models\Ingrediente;

/**
 * Cálculo nutricional de uma formulação de ração — a ÚNICA fonte desses números.
 *
 * Usada na pré-visualização (tela final do app, antes de salvar), ao salvar os
 * ingredientes e, por consequência, na formulação salva e no PDF. Assim os
 * valores mostrados antes de salvar são exatamente os que ficam gravados.
 *
 * Convenções:
 * - proporcao_pct é a participação do ingrediente na MATÉRIA SECA da dieta;
 * - o consumo em matéria natural (MN) sai do consumo em MS dividido pelo MS% do ingrediente;
 * - o preço é por kg de matéria natural (como o produtor compra), então o custo usa o consumo em MN;
 * - cada valor é arredondado com as mesmas casas decimais da coluna onde é gravado,
 *   para que a prévia e o banco nunca divirjam por arredondamento.
 */
final class CalculadoraFormulacao
{
    /**
     * @param  float  $cmsKg  Consumo de matéria seca exigido (kg/animal/dia)
     * @param  list<array{ingrediente: Ingrediente, tipo: string, proporcao_pct: float|int|string, preco_kg_local: float|int|string|null}>  $itens
     * @return array{itens: list<array<string, mixed>>, totais: array<string, float>}
     */
    public function calcular(float $cmsKg, array $itens): array
    {
        // Mesmas casas decimais de programas_racao.exig_cms_kg
        $cms = round($cmsKg, 3);

        $resultado = [];
        $totais = [
            'proporcao_pct'    => 0.0,
            'consumo_ms_kg'    => 0.0,
            'consumo_mn_kg'    => 0.0,
            'contrib_ndt_kg'   => 0.0,
            'contrib_pb_g'     => 0.0,
            'contrib_pdr_g'    => 0.0,
            'contrib_elm_mcal' => 0.0,
            'contrib_elg_mcal' => 0.0,
            'contrib_ca_g'     => 0.0,
            'contrib_p_g'      => 0.0,
            'custo_animal_dia' => 0.0,
        ];

        foreach ($itens as $item) {
            /** @var Ingrediente $ing */
            $ing = $item['ingrediente'];

            $proporcao = round((float) $item['proporcao_pct'], 2);
            $precoKg   = round((float) ($item['preco_kg_local'] ?? $ing->preco_kg ?? 0), 4);
            $msPct     = (float) $ing->ms_pct;

            $consumoMS = $cms * ($proporcao / 100);
            $consumoMN = $msPct > 0 ? $consumoMS / ($msPct / 100) : 0.0;
            $contribPB = $consumoMS * ((float) $ing->pb_pct / 100) * 1000;

            $linha = [
                'ingrediente_id'   => $ing->id,
                'nome'             => $ing->nome,
                'ms_pct'           => $msPct,
                'tipo'             => $item['tipo'],
                'proporcao_pct'    => $proporcao,
                'preco_kg_local'   => $precoKg,
                'consumo_ms_kg'    => round($consumoMS, 3),
                'consumo_mn_kg'    => round($consumoMN, 3),
                'contrib_ndt_kg'   => round($consumoMS * ((float) $ing->ndt_pct / 100), 3),
                'contrib_pb_g'     => round($contribPB, 2),
                'contrib_pdr_g'    => round($contribPB * ((float) $ing->pdr_pct / 100), 2),
                'contrib_elm_mcal' => round($consumoMS * (float) $ing->elm_mcal, 3),
                'contrib_elg_mcal' => round($consumoMS * (float) $ing->elg_mcal, 3),
                'contrib_ca_g'     => round($consumoMS * ((float) $ing->ca_pct / 100) * 1000, 2),
                'contrib_p_g'      => round($consumoMS * ((float) $ing->p_pct / 100) * 1000, 2),
                'custo_animal_dia' => round($consumoMN * $precoKg, 4),
            ];

            foreach ($totais as $campo => $soma) {
                $totais[$campo] = $soma + $linha[$campo];
            }

            $resultado[] = $linha;
        }

        // Totais = soma dos valores já arredondados (igual à soma das linhas gravadas)
        $casas = [
            'proporcao_pct' => 2, 'consumo_ms_kg' => 3, 'consumo_mn_kg' => 3,
            'contrib_ndt_kg' => 3, 'contrib_pb_g' => 2, 'contrib_pdr_g' => 2,
            'contrib_elm_mcal' => 3, 'contrib_elg_mcal' => 3,
            'contrib_ca_g' => 2, 'contrib_p_g' => 2, 'custo_animal_dia' => 4,
        ];
        foreach ($totais as $campo => $soma) {
            $totais[$campo] = round($soma, $casas[$campo]);
        }

        return ['itens' => $resultado, 'totais' => $totais];
    }
}
