<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class CadastroPendente extends Model
{
    use HasUuids, Prunable;

    protected $table = 'cadastros_pendentes';

    protected $fillable = [
        'email',
        'dados',
        'codigo',
        'expira_em',
        'tentativas',
        'ultimo_envio_em',
    ];

    protected $hidden = [
        'dados',
        'codigo',
    ];

    protected $casts = [
        'dados'           => 'encrypted:array',
        'expira_em'       => 'datetime',
        'ultimo_envio_em' => 'datetime',
        'tentativas'      => 'integer',
    ];

    // Cadastros não confirmados em 24h são apagados pelo `php artisan model:prune`
    public function prunable(): Builder
    {
        return static::where('updated_at', '<', now()->subDay());
    }
}
