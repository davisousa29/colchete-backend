<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['chave', 'valor', 'descricao'];

    public static function valor(string $chave): ?string
    {
        $valor = static::find($chave)?->valor;

        return filled($valor) ? $valor : null;
    }
}
