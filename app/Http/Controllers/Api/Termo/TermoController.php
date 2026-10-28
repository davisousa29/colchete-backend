<?php

namespace App\Http\Controllers\Api\Termo;

use App\Http\Controllers\Controller;
use App\Models\Termo;
use App\Services\TermosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TermoController extends Controller
{
    public function __construct(private TermosService $termos) {}

    // Público: usado pelo modal do cadastro
    public function vigentes()
    {
        return response()->json([
            'termos' => $this->termos->vigentes()->values()->map(fn (Termo $t) => $this->formatar($t)),
        ]);
    }

    // Logado: o que falta aceitar (vazio = em dia)
    public function pendentes(Request $request)
    {
        return response()->json([
            'termos' => $this->termos->pendentes($request->user())->map(fn (Termo $t) => $this->formatar($t)),
        ]);
    }

    // Logado: registra o aceite das versões vigentes
    public function aceitar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'termos'   => 'required|array|min:1',
            'termos.*' => 'required|uuid',
        ], [
            'termos.required' => 'Informe os termos aceitos.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!$this->termos->saoVigentes($request->termos)) {
            return response()->json([
                'message' => 'Os termos foram atualizados. Leia a versão mais recente.',
                'code'    => 'termos_desatualizados',
            ], 409);
        }

        $this->termos->registrarAceite(
            $request->user(),
            $request->termos,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Termos aceitos.']);
    }

    private function formatar(Termo $termo): array
    {
        return [
            'id'                => $termo->id,
            'tipo'              => $termo->tipo,
            'titulo'            => $termo->titulo,
            'versao'            => $termo->versao,
            'conteudo'          => $termo->conteudo,
            'resumo_alteracoes' => $termo->resumo_alteracoes,
            'publicado_em'      => $termo->publicado_em?->toIso8601String(),
        ];
    }
}
