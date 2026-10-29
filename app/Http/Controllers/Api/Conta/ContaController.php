<?php

namespace App\Http\Controllers\Api\Conta;

use App\Exceptions\ExclusaoContaException;
use App\Http\Controllers\Controller;
use App\Services\ExclusaoContaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContaController extends Controller
{
    public function __construct(private ExclusaoContaService $exclusao) {}

    // ── Exclusão da própria conta (confirmada com a senha) ────────────────────
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ], [
            'password.required' => 'Digite sua senha para confirmar a exclusão.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->exclusao->excluir($request->user(), $request->password);
        } catch (ExclusaoContaException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        // O usuário não existe mais; ainda assim, o token vai para a lista negra
        try {
            auth()->invalidate();
        } catch (\Throwable) {
            // Token já inválido: nada a fazer
        }

        return response()->json([
            'message' => 'Sua conta foi excluída.',
        ]);
    }
}
