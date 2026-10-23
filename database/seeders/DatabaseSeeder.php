<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dados de referência do sistema. Todos os seeders são idempotentes:
 * podem rodar em qualquer banco (novo ou existente), quantas vezes for preciso.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EspeciesSeeder::class,
            RacasSeeder::class,
            CategoriaAnimaisSeeder::class,
            SistemasProducaoSeeder::class,
            CoeficientesNutricionaisSeeder::class,
            IngredientesSeeder::class,
            Metodos2faSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
