<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\SyncsReferenceData;

class SistemasProducaoSeeder extends Seeder
{
    use SyncsReferenceData;

    public function run(): void
    {
        $bovino  = DB::table('especies')->where('nome', 'Bovino')->first();
        $suino   = DB::table('especies')->where('nome', 'Suíno')->first();
        $ave     = DB::table('especies')->where('nome', 'Ave')->first();
        $ovino   = DB::table('especies')->where('nome', 'Ovino')->first();
        $caprino = DB::table('especies')->where('nome', 'Caprino')->first();
        $equino  = DB::table('especies')->where('nome', 'Equino')->first();

        $sistemas = [
            // ── Bovinos ───────────────────────────────────────────────────────
            [
                'especie_id'  => $bovino->id,
                'nome'        => 'Pasto',
                'descricao'   => 'Animais em pastagem, com ou sem suplementação',
            ],
            [
                'especie_id'  => $bovino->id,
                'nome'        => 'Confinamento',
                'descricao'   => 'Animais confinados em baias com dieta total no cocho',
            ],
            [
                'especie_id'  => $bovino->id,
                'nome'        => 'Semi-confinamento',
                'descricao'   => 'Pastagem com suplementação intensiva no cocho',
            ],

            // ── Suínos ────────────────────────────────────────────────────────
            [
                'especie_id'  => $suino->id,
                'nome'        => 'Confinamento intensivo',
                'descricao'   => 'Sistema intensivo em galpões com dieta controlada',
            ],
            [
                'especie_id'  => $suino->id,
                'nome'        => 'Semi-intensivo',
                'descricao'   => 'Acesso a piquetes com suplementação',
            ],

            // ── Aves ──────────────────────────────────────────────────────────
            [
                'especie_id'  => $ave->id,
                'nome'        => 'Confinamento (galpão)',
                'descricao'   => 'Sistema intensivo em galpões climatizados',
            ],
            [
                'especie_id'  => $ave->id,
                'nome'        => 'Caipira / Semi-intensivo',
                'descricao'   => 'Acesso a área externa com suplementação',
            ],

            // ── Ovinos ────────────────────────────────────────────────────────
            [
                'especie_id'  => $ovino->id,
                'nome'        => 'Pasto',
                'descricao'   => 'Criação extensiva em pastagem',
            ],
            [
                'especie_id'  => $ovino->id,
                'nome'        => 'Confinamento',
                'descricao'   => 'Sistema intensivo em aprisco',
            ],
            [
                'especie_id'  => $ovino->id,
                'nome'        => 'Semi-confinamento',
                'descricao'   => 'Pastagem com suplementação no cocho',
            ],

            // ── Caprinos ──────────────────────────────────────────────────────
            [
                'especie_id'  => $caprino->id,
                'nome'        => 'Pasto',
                'descricao'   => 'Criação extensiva em pastagem / caatinga',
            ],
            [
                'especie_id'  => $caprino->id,
                'nome'        => 'Confinamento',
                'descricao'   => 'Sistema intensivo em aprisco',
            ],

            // ── Equinos ───────────────────────────────────────────────────────
            [
                'especie_id'  => $equino->id,
                'nome'        => 'Pasto',
                'descricao'   => 'Criação extensiva em pastagem',
            ],
            [
                'especie_id'  => $equino->id,
                'nome'        => 'Estabulado',
                'descricao'   => 'Animal em baia com dieta controlada',
            ],
            [
                'especie_id'  => $equino->id,
                'nome'        => 'Semi-estabulado',
                'descricao'   => 'Piquete com suplementação em baia',
            ],
        ];

        foreach ($sistemas as $sistema) {
            $this->sync('sistemas_producao',
                ['especie_id' => $sistema['especie_id'], 'nome' => $sistema['nome']],
                ['descricao' => $sistema['descricao']],
                ['ativo' => true],
            );
        }
    }
}
