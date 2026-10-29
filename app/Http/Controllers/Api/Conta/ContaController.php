<?php

namespace App\Http\Controllers\Api\Conta;

use App\Exceptions\ExclusaoContaException;
use App\Http\Controllers\Controller;
use App\Mail\SubscriptionNoticeMail;
use App\Models\User;
use App\Services\ExclusaoContaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ContaController extends Controller
{
    public function __construct(private ExclusaoContaService $exclusao) {}

    // ── Alteração de senha pelo usuário logado (confirmada com a senha atual) ─
    public function alterarSenha(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->password) {
            return response()->json([
                'message' => 'Esta conta foi criada com o Google e não tem senha. Para definir uma, use "Esqueci minha senha" na tela de login.',
            ], 422);
        }

        // Mensagens padrão em pt_BR (lang/pt_BR); só os nomes dos campos são ajustados
        $validator = Validator::make($request->all(), [
            'senha_atual' => 'required|string',
            'password'    => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols(),
            ],
        ], [], [
            'senha_atual' => 'senha atual',
            'password'    => 'nova senha',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->senha_atual, $user->password)) {
            return response()->json([
                'message' => 'Senha atual incorreta.',
                'errors'  => ['senha_atual' => ['Senha atual incorreta.']],
            ], 422);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'A nova senha deve ser diferente da senha atual.',
                'errors'  => ['password' => ['A nova senha deve ser diferente da senha atual.']],
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $this->avisarAlteracaoDeSenha($user);

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
        ]);
    }

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

    // Se não foi o dono quem trocou, ele fica sabendo e pode recuperar a conta
    private function avisarAlteracaoDeSenha(User $user): void
    {
        try {
            Mail::to($user->email)->send(new SubscriptionNoticeMail(
                $user->name,
                'Sua senha do Colchete foi alterada',
                'A senha da sua conta acabou de ser alterada pelo aplicativo. '
                . 'Se não foi você, use "Esqueci minha senha" na tela de login para recuperar o acesso '
                . 'e responda este e-mail.',
            ));
        } catch (\Throwable $e) {
            // A senha já foi trocada: a falha do e-mail não desfaz nada
            Log::warning('Falha ao enviar aviso de alteração de senha', ['erro' => $e->getMessage()]);
        }
    }
}
