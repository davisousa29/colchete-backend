<?php

namespace Tests\Unit;

use App\Models\ProgramaRacao;
use App\Models\RacaoIngrediente;
use App\Services\Racao\IndicadoresFormulacao;
use Tests\TestCase;

class IndicadoresFormulacaoTest extends TestCase
{
    private function programa(): ProgramaRacao
    {
        $programa = (new ProgramaRacao)->forceFill([
            'peso_inicial_kg' => '300.00', 'peso_final_kg' => '450.00', 'gmd_kg' => '1.200',
            'exig_cms_kg' => '7.600', 'exig_ndt_kg' => null, 'exig_pb_g' => '1000',
            'exig_elm_mcal' => '6.75', 'exig_ca_g' => '26', 'exig_p_g' => '17',
            'quantidade_animais' => 10, 'custo_animal_dia' => '13.0705',
        ]);

        $programa->setRelation('ingredientes', collect([
            (new RacaoIngrediente)->forceFill(['tipo' => 'concentrado', 'proporcao_pct' => '76', 'consumo_ms_kg' => '5.776', 'consumo_mn_kg' => '6.639', 'contrib_pb_g' => '900', 'contrib_ndt_kg' => '4.9', 'contrib_elm_mcal' => '12', 'contrib_ca_g' => '2', 'contrib_p_g' => '16']),
            (new RacaoIngrediente)->forceFill(['tipo' => 'volumoso_principal', 'proporcao_pct' => '24', 'consumo_ms_kg' => '1.824', 'consumo_mn_kg' => '6.080', 'contrib_pb_g' => '130', 'contrib_ndt_kg' => '1.2', 'contrib_elm_mcal' => '2.6', 'contrib_ca_g' => '4.5', 'contrib_p_g' => '3.6']),
        ]));

        return $programa;
    }

    public function test_dias_ate_o_peso_e_conversao_alimentar(): void
    {
        $r = (new IndicadoresFormulacao)->calcular($this->programa());

        $this->assertSame(125, $r['dias_ate_peso']);          // (450 − 300) ÷ 1,2
        $this->assertSame(6.33, $r['conversao_alimentar']);   // 7,6 ÷ 1,2
    }

    public function test_totais_e_grupos_por_lote(): void
    {
        $r = (new IndicadoresFormulacao)->calcular($this->programa());

        $this->assertSame(100.0, $r['totais']['proporcao_pct']);
        $this->assertSame(7.6, $r['totais']['consumo_ms_kg']);
        $this->assertSame(66.4, $r['grupos']['concentrado']['mn_kg']); // 6,639 × 10 animais
        $this->assertSame(24.0, $r['grupos']['volumoso']['pct']);
    }

    public function test_balanco_sem_ndt_exigido_omite_a_linha(): void
    {
        $r = (new IndicadoresFormulacao)->calcular($this->programa());

        $this->assertNotContains('Nutrientes Digestíveis Totais (NDT)', array_column($r['balanco'], 'label'));
        $this->assertSame('alto', $r['balanco'][0]['status']); // ELm: 14,6 de 6,75
    }
}
