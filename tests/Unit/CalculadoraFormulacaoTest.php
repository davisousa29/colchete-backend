<?php

namespace Tests\Unit;

use App\Models\Ingrediente;
use App\Services\Racao\CalculadoraFormulacao;
use Tests\TestCase;

class CalculadoraFormulacaoTest extends TestCase
{
    private function ingrediente(array $dados): Ingrediente
    {
        return (new Ingrediente)->forceFill($dados);
    }

    public function test_calcula_consumo_contribuicoes_e_custo_por_ingrediente(): void
    {
        $milho = $this->ingrediente([
            'id' => 'milho', 'nome' => 'Milho', 'ms_pct' => '87.00', 'ndt_pct' => '85.00',
            'pb_pct' => '9.00', 'pdr_pct' => '50.00', 'elm_mcal' => '2.180', 'elg_mcal' => '1.500',
            'ca_pct' => '0.03', 'p_pct' => '0.28', 'preco_kg' => '1.5000',
        ]);

        $r = (new CalculadoraFormulacao)->calcular(10, [
            ['ingrediente' => $milho, 'tipo' => 'concentrado', 'proporcao_pct' => 100, 'preco_kg_local' => null],
        ]);

        $linha = $r['itens'][0];
        $this->assertSame(10.0, $linha['consumo_ms_kg']);
        $this->assertSame(11.494, $linha['consumo_mn_kg']);   // 10 / 0,87
        $this->assertSame(900.0, $linha['contrib_pb_g']);     // 10 kg × 9% × 1000
        $this->assertSame(21.8, $linha['contrib_elm_mcal']);  // 10 kg × 2,18
        $this->assertSame(8.5, $linha['contrib_ndt_kg']);
        $this->assertSame(17.2414, $linha['custo_animal_dia']); // MN × preço padrão
    }

    public function test_preco_local_substitui_o_padrao_e_totais_somam_as_linhas(): void
    {
        $milho = $this->ingrediente(['id' => 'm', 'nome' => 'Milho', 'ms_pct' => 87, 'pb_pct' => 9, 'preco_kg' => 1.5]);
        $silagem = $this->ingrediente(['id' => 's', 'nome' => 'Silagem', 'ms_pct' => 30, 'pb_pct' => 7.5, 'preco_kg' => 0.4]);

        $r = (new CalculadoraFormulacao)->calcular(7.64234, [
            ['ingrediente' => $milho, 'tipo' => 'concentrado', 'proporcao_pct' => '76', 'preco_kg_local' => null],
            ['ingrediente' => $silagem, 'tipo' => 'volumoso_principal', 'proporcao_pct' => 24, 'preco_kg_local' => '0.50'],
        ]);

        $this->assertSame(0.5, $r['itens'][1]['preco_kg_local']);
        $this->assertSame(1.834, $r['itens'][1]['consumo_ms_kg']); // CMS arredondado a 3 casas, como no banco
        $this->assertSame(100.0, $r['totais']['proporcao_pct']);
        $this->assertSame(
            round($r['itens'][0]['contrib_pb_g'] + $r['itens'][1]['contrib_pb_g'], 2),
            $r['totais']['contrib_pb_g'],
        );
    }
}
