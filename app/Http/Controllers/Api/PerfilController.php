<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultorProfile;
use App\Models\FazendeiroProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PerfilController extends Controller
{
    // ── Retorna o usuário e o perfil do usuário autenticado ───────────────────

    public function show()
    {
        $user = auth()->user();

        return response()->json([
            'user'   => $user,
            'perfil' => $this->getPerfil($user),
        ]);
    }

    // ── Atualiza os dados pessoais editáveis e o perfil ───────────────────────
    // Editáveis: nome, telefone, WhatsApp e — só se ainda estiver vazia — a data de nascimento.
    // E-mail, CPF e username não mudam por aqui.

    public function save(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $regras = array_merge(
            $this->regrasDadosPessoais($user),
            $user->role === 'consultor' ? $this->regrasConsultor() : $this->regrasFazendeiro(),
        );

        $validator = Validator::make($request->all(), $regras, $this->mensagens());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $perfil = DB::transaction(function () use ($request, $user) {
            $this->atualizarDadosPessoais($request, $user);

            return $user->role === 'consultor'
                ? $this->salvarConsultor($request, $user)
                : $this->salvarFazendeiro($request, $user);
        });

        return response()->json([
            'message' => 'Perfil salvo com sucesso.',
            'user'    => $user->fresh(),
            'perfil'  => $perfil,
        ]);
    }

    // ── Dados pessoais ────────────────────────────────────────────────────────

    private function regrasDadosPessoais(User $user): array
    {
        $regras = [
            'name'     => 'required|string|min:3|max:255',
            'phone'    => 'nullable|digits:11',
            'whatsapp' => 'nullable|digits:11',
        ];

        // A data de nascimento só pode ser informada uma vez (contas antigas e do Google)
        if (!$user->data_nascimento) {
            $idadeMinima = now()->subYears(18)->toDateString();
            $regras['data_nascimento'] = "nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:{$idadeMinima}";
        }

        return $regras;
    }

    private function atualizarDadosPessoais(Request $request, User $user): void
    {
        $user->name     = trim($request->name);
        $user->phone    = $request->phone ?: null;
        $user->whatsapp = $request->whatsapp ?: null;

        if (!$user->data_nascimento && $request->filled('data_nascimento')) {
            $user->data_nascimento = $request->data_nascimento;
        }

        $user->save();
    }

    // ── Perfil do consultor ───────────────────────────────────────────────────

    private function regrasConsultor(): array
    {
        return [
            'crea_number'    => 'nullable|string|max:20',
            'specialization' => 'required|string|max:255',
            'bio'            => 'nullable|string|max:1000',
            'location_state' => 'required|string|size:2',
            'location_city'  => 'required|string|max:255',
        ];
    }

    private function salvarConsultor(Request $request, User $user): ConsultorProfile
    {
        $perfil = ConsultorProfile::firstOrNew(['user_id' => $user->id]);

        $perfil->fill([
            'user_id'        => $user->id,
            'crea_number'    => $request->crea_number,
            'specialization' => $request->specialization,
            'bio'            => $request->bio,
            'location_state' => $request->location_state,
            'location_city'  => $request->location_city,
        ]);

        $perfil->save();

        return $perfil;
    }

    // ── Perfil do fazendeiro ──────────────────────────────────────────────────

    private function regrasFazendeiro(): array
    {
        return [
            'cpf_cnpj'       => 'nullable|string|max:18',
            'location_state' => 'nullable|string|size:2',
            'location_city'  => 'nullable|string|max:255',
        ];
    }

    private function salvarFazendeiro(Request $request, User $user): FazendeiroProfile
    {
        $perfil = FazendeiroProfile::firstOrNew(['user_id' => $user->id]);

        $perfil->fill([
            'user_id'        => $user->id,
            'cpf_cnpj'       => $request->cpf_cnpj,
            'location_state' => $request->location_state,
            'location_city'  => $request->location_city,
        ]);

        $perfil->save();

        return $perfil;
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    private function mensagens(): array
    {
        return [
            'name.required'                   => 'O nome é obrigatório.',
            'name.min'                        => 'O nome deve ter no mínimo 3 letras.',
            'phone.digits'                    => 'O telefone deve ter 11 dígitos (DDD + número).',
            'whatsapp.digits'                 => 'O WhatsApp deve ter 11 dígitos (DDD + número).',
            'data_nascimento.date_format'     => 'Informe uma data de nascimento válida.',
            'data_nascimento.after_or_equal'  => 'Informe uma data de nascimento válida.',
            'data_nascimento.before_or_equal' => 'É preciso ter pelo menos 18 anos.',
            'specialization.required'         => 'Selecione uma especialização.',
            'location_state.required'         => 'Selecione o seu estado.',
            'location_city.required'          => 'Selecione a sua cidade.',
        ];
    }

    private function getPerfil(User $user)
    {
        if ($user->role === 'consultor') {
            return ConsultorProfile::where('user_id', $user->id)->first();
        }

        return FazendeiroProfile::where('user_id', $user->id)->first();
    }
}
