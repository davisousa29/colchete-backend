<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\CadastroException;
use App\Http\Controllers\Controller;
use App\Rules\ValidCpf;
use App\Services\CadastroService;
use App\Support\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CadastroController extends Controller
{
    public function __construct(private CadastroService $cadastro) {}

    // ── 1. Valida o formulário, guarda como pendente e envia o código ─────────
    public function iniciar(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim($request->email ?? '')),
            'cpf'   => Document::onlyDigits($request->cpf ?? ''),
        ]);

        $idadeMinima = now()->subYears(18)->toDateString();

        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|min:3|max:255',
            'email'           => 'required|string|email|max:255|unique:users',
            'cpf'             => ['required', 'string', 'size:11', 'unique:users', new ValidCpf()],
            'data_nascimento' => "required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:{$idadeMinima}",
            'phone'           => 'nullable|string|min:11|max:11',
            'username'        => 'required|string|min:3|max:30|unique:users|alpha_dash',
            'whatsapp'        => 'nullable|string|min:11|max:11',
            'password'        => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols(),
            ],
            'aceite_termos'   => 'accepted',
            'termos'          => 'required|array|min:1',
            'termos.*'        => 'required|uuid',
        ], [
            // ── Campos obrigatórios ─────────────────────────────
            'name.required'            => 'O nome é obrigatório.',
            'email.required'           => 'O email é obrigatório.',
            'cpf.required'             => 'O CPF é obrigatório.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'username.required'        => 'O nome de usuário é obrigatório.',
            'password.required'        => 'A senha é obrigatória.',

            // ── Formatos inválidos ──────────────────────────────
            'name.min'                        => 'O nome deve ter no mínimo 3 letras.',
            'email.email'                     => 'Informe um email válido.',
            'cpf.size'                        => 'O CPF deve conter 11 dígitos.',
            'data_nascimento.date_format'     => 'Informe uma data de nascimento válida.',
            'data_nascimento.after_or_equal'  => 'Informe uma data de nascimento válida.',
            'data_nascimento.before_or_equal' => 'É preciso ter pelo menos 18 anos para criar uma conta.',
            'phone.min'                       => 'O número de telefone deve ter no mínimo 11 caracteres.',
            'phone.max'                       => 'O número de telefone deve ter no máximo 11 caracteres.',
            'whatsapp.min'                    => 'O número de whatsapp deve ter no mínimo 11 caracteres.',
            'whatsapp.max'                    => 'O número de whatsapp deve ter no máximo 11 caracteres.',
            'username.min'                    => 'O nome de usuário deve ter no mínimo 3 caracteres.',
            'username.alpha_dash'             => 'O nome de usuário só pode conter letras, números, traços e underlines.',

            // ── Unicidade ───────────────────────────────────────
            'email.unique'    => 'Não é possível utilizar este email.',
            'cpf.unique'      => 'Não é possível utilizar este CPF.',
            'username.unique' => 'Não é possível utilizar este nome.',

            // ── Senha ───────────────────────────────────────────
            'password.confirmed' => 'As senhas não coincidem.',
            'password.min'       => 'A senha deve ter no mínimo 8 caracteres.',
            'password.mixed'     => 'A senha deve conter letras maiúsculas e minúsculas.',
            'password.letters'   => 'A senha deve conter pelo menos uma letra.',
            'password.numbers'   => 'A senha deve conter pelo menos um número.',
            'password.symbols'   => 'A senha deve conter pelo menos um caractere especial.',

            // ── Termos ──────────────────────────────────────────
            'aceite_termos.accepted' => 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.',
            'termos.required'        => 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $segundos = $this->cadastro->iniciar($validator->validated());
        } catch (CadastroException $e) {
            return $this->erro($e);
        }

        return response()->json([
            'message'               => 'Enviamos um código de confirmação para o seu e-mail.',
            'email'                 => $request->email,
            'segundos_para_reenvio' => $segundos,
        ], 202);
    }

    // ── 2. Confere o código e cria a conta ────────────────────────────────────
    public function confirmar(Request $request)
    {
        $request->merge(['email' => strtolower(trim($request->email ?? ''))]);

        $validator = Validator::make($request->all(), [
            'email'  => 'required|string|email',
            'codigo' => 'required|digits:6',
        ], [
            'codigo.required' => 'Digite o código recebido.',
            'codigo.digits'   => 'O código deve ter 6 dígitos.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = $this->cadastro->confirmar(
                $request->email,
                $request->codigo,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (CadastroException $e) {
            return $this->erro($e);
        }

        $token = auth()->login($user);

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // ── 3. Reenvia o código ───────────────────────────────────────────────────
    public function reenviar(Request $request)
    {
        $request->merge(['email' => strtolower(trim($request->email ?? ''))]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Informe um email válido.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $segundos = $this->cadastro->reenviar($request->email);
        } catch (CadastroException $e) {
            return $this->erro($e);
        }

        return response()->json([
            'message'               => 'Enviamos um novo código para o seu e-mail.',
            'segundos_para_reenvio' => $segundos,
        ]);
    }

    private function erro(CadastroException $e)
    {
        return response()->json(
            ['message' => $e->getMessage(), ...$e->extra],
            $e->status,
        );
    }
}
