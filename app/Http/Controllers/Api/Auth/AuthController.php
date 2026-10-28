<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim($request->email ?? '')),
        ]);

        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Verifica as credenciais SEM logar ainda (para checar o 2FA antes)
        $token = auth()->attempt([
            'email'    => $request->email,
            'password' => $request->password,
            'active'   => true,
        ]);

        if (!$token) {
            return response()->json([
                'message' => 'Email ou senha incorretos.',
            ], 401);
        }

        $user   = auth()->user();
        $config = $user->configuracao2fa;

        // Se tem 2FA ativo, NÃO devolve o token — pede o segundo fator
        if ($config && $config->ativo && $config->confirmado_em) {
            // Invalida o token recém-gerado (o login só se completa após o 2FA)
            auth()->logout();

            return response()->json([
                'message'         => 'Verificação em duas etapas necessária.',
                'requer_2fa'      => true,
                'metodos'         => $this->metodosDisponiveis($config),
                'email'           => $user->email,
            ], 200);
        }

        // Sem 2FA — login normal
        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // ── Retorna os métodos de 2FA disponíveis ─────────────
    private function metodosDisponiveis($config): array
    {
        return \App\Models\Metodo2fa::where('ativo', true)
            ->orderBy('ordem')
            ->get(['chave', 'nome', 'descricao'])
            ->toArray();
    }

    // ── Usuário autenticado ───────────────────────────────────────────────────

    public function me()
    {
        return response()->json([
            'user' => auth()->user(),
        ]);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    // ── Renovação do token ────────────────────────────────────────────────────

    public function refresh()
    {
        try {
            // Valida o token (mesmo vencido), confere a janela de renovação,
            // gera um token novo e põe o antigo na lista negra
            $token = auth()->refresh();
        } catch (JWTException $e) {
            // Fora da janela, já renovado (lista negra), inválido ou ausente
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente.',
            ], 401);
        }

        $user = auth()->setToken($token)->user();

        // Conta desativada depois do login não pode continuar renovando
        if (!$user || !$user->active) {
            auth()->invalidate();

            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente.',
            ], 401);
        }

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }
}
