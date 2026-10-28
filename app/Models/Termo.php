<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Termo extends Model
{
    use HasUuids;

    protected $table = 'termos';

    // Tipos existentes e o título exibido de cada um
    public const TIPOS = [
        'uso'         => 'Termos de Uso',
        'privacidade' => 'Política de Privacidade',
    ];

    protected $fillable = [
        'tipo',
        'versao',
        'titulo',
        'conteudo',
        'resumo_alteracoes',
        'exige_novo_aceite',
        'publicado_em',
    ];

    protected $casts = [
        'versao'            => 'integer',
        'exige_novo_aceite' => 'boolean',
        'publicado_em'      => 'datetime',
    ];

    public function aceites()
    {
        return $this->hasMany(AceiteTermo::class, 'termo_id');
    }
}
