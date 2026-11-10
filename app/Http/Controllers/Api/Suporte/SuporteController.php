<?php

namespace App\Http\Controllers\Api\Suporte;

use App\Http\Controllers\Controller;
use App\Models\Configuracao;
use Illuminate\Http\JsonResponse;

class SuporteController extends Controller
{
    public function contato(): JsonResponse
    {
        return response()->json([
            'whatsapp' => Configuracao::valor('suporte_whatsapp'),
            'email' => Configuracao::valor('suporte_email'),
            'mensagem' => Configuracao::valor('suporte_mensagem'),
        ]);
    }
}
