<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AceiteTermo extends Model
{
    use HasUuids;

    protected $table = 'aceites_termos';

    protected $fillable = [
        'user_id',
        'termo_id',
        'aceito_em',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'aceito_em' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function termo()
    {
        return $this->belongsTo(Termo::class, 'termo_id');
    }
}
