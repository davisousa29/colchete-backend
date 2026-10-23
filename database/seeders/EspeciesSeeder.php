<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SyncsReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EspeciesSeeder extends Seeder
{
    use SyncsReferenceData;
    public function run(): void
    {
        $especies = [
            [
                'nome'             => 'Bovino',
                'nome_cientifico'  => 'Bos taurus / Bos indicus',
            ],
            [
                'nome'             => 'Suíno',
                'nome_cientifico'  => 'Sus scrofa domesticus',
            ],
            [
                'nome'             => 'Ave',
                'nome_cientifico'  => 'Gallus gallus domesticus',
            ],
            [
                'nome'             => 'Ovino',
                'nome_cientifico'  => 'Ovis aries',
            ],
            [
                'nome'             => 'Caprino',
                'nome_cientifico'  => 'Capra aegagrus hircus',
            ],
            [
                'nome'             => 'Equino',
                'nome_cientifico'  => 'Equus caballus',
            ],
        ];

        foreach ($especies as $especie) {
            $this->sync('especies',
                ['nome' => $especie['nome']],
                ['nome_cientifico' => $especie['nome_cientifico']],
                ['ativo' => true],
            );
        }
    }
}
