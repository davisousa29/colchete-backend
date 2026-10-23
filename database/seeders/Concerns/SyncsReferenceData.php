<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sincroniza dados de referência sem duplicar: procura o registro pela chave
 * natural ($keys); se existe, atualiza os valores mantendo o ID; se não existe,
 * cria com um UUID novo. Pode rodar quantas vezes for preciso.
 */
trait SyncsReferenceData
{
    /**
     * @param  array  $keys          Colunas que identificam o registro (ex.: ['nome' => 'Bovino'])
     * @param  array  $values        Colunas atualizadas a cada execução
     * @param  array  $onlyOnCreate  Colunas gravadas só na criação (ex.: ativo, preço editável)
     * @return string                O ID do registro (existente ou criado)
     */
    protected function sync(string $table, array $keys, array $values, array $onlyOnCreate = []): string
    {
        $existing = DB::table($table)->where($keys)->first();

        if ($existing) {
            DB::table($table)
                ->where('id', $existing->id)
                ->update($values + ['updated_at' => now()]);

            return $existing->id;
        }

        $id = (string) Str::uuid();

        DB::table($table)->insert(
            ['id' => $id] + $keys + $values + $onlyOnCreate + [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $id;
    }
}
