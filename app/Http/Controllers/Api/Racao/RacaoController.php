<?php

namespace App\Http\Controllers\Api\Racao;

use App\Http\Controllers\Controller;
use App\Models\ProgramaRacao;
use App\Models\RacaoIngrediente;
use App\Models\Ingrediente;
use App\Services\Racao\CalculadoraFormulacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RacaoController extends Controller
{
    // ── Lista programas de ração do contrato ──────────────────────────────────
    public function index(Request $request)
    {
        $user = auth()->user();

        $programas = ProgramaRacao::where('criado_por', $user->id)
            ->with(['especie', 'raca', 'categoria', 'sistema'])
            ->when($request->filled('contrato_id'), fn($q) =>
            $q->where('contrato_id', $request->contrato_id)
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'programas' => $programas,
        ]);
    }

    // ── Exibe um programa completo com ingredientes ───────────────────────────
    public function show($id)
    {
        $user = auth()->user();

        $programa = ProgramaRacao::where('criado_por', $user->id)
            ->with([
                'especie', 'raca', 'categoria', 'sistema',
                'ingredientes.ingrediente',
                'contrato',
            ])
            ->findOrFail($id);

        return response()->json([
            'programa' => $programa,
        ]);
    }

    // ── Cria um novo programa de ração ────────────────────────────────────────

    public function store(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'contrato_id'      => 'nullable|uuid|exists:contratos,id',
            'nome'             => 'required|string|max:255',
            'especie_id'       => 'required|uuid|exists:especies,id',
            'raca_id'          => 'required|uuid|exists:racas,id',
            'categoria_id'     => 'required|uuid|exists:categorias_animais,id',
            'sistema_id'       => 'required|uuid|exists:sistemas_producao,id',
            'peso_inicial_kg'  => 'required|numeric|min:1',
            'peso_final_kg'    => 'required|numeric|min:1|gte:peso_inicial_kg',
            'gmd_kg'           => 'required|numeric|min:0.1|max:3',
            'quantidade_animais' => 'required|integer|min:1',
            'tipo_aplicacao'   => 'required|in:individual,lote',
            'identificacao_animal' => 'nullable|string|max:100',
            'data_inicio'      => 'nullable|date',
            'data_fim'         => 'nullable|date|after_or_equal:data_inicio',
            'observacoes'      => 'nullable|string',

            // Exigências calculadas (vindas do ExigenciaController)
            'exig_cms_kg'      => 'nullable|numeric',
            'exig_ndt_kg'      => 'nullable|numeric',
            'exig_pb_g'        => 'nullable|numeric',
            'exig_elm_mcal'    => 'nullable|numeric',
            'exig_elg_mcal'    => 'nullable|numeric',
            'exig_ca_g'        => 'nullable|numeric',
            'exig_p_g'         => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $pesoMedio = ($request->peso_inicial_kg + $request->peso_final_kg) / 2;

        $programa = ProgramaRacao::create([
            ...$request->only([
                'contrato_id', 'nome', 'status',
                'especie_id', 'raca_id', 'categoria_id', 'sistema_id',
                'peso_inicial_kg', 'peso_final_kg', 'gmd_kg',
                'quantidade_animais', 'tipo_aplicacao',
                'identificacao_animal', 'lote_id',
                'exig_cms_kg', 'exig_ndt_kg', 'exig_pb_g',
                'exig_elm_mcal', 'exig_elg_mcal', 'exig_ca_g', 'exig_p_g',
                'data_inicio', 'data_fim', 'observacoes',
            ]),
            'criado_por'              => $user->id,
            'peso_medio_kg'           => $pesoMedio,
            'status'                  => 'rascunho',
            'referencia_nutricional'  => 'BR-CORTE 2016',
        ]);

        return response()->json([
            'message'  => 'Programa de ração criado com sucesso.',
            'programa' => $programa->load(['especie', 'raca', 'categoria', 'sistema']),
        ], 201);
    }

    // ── Calcula a formulação sem salvar (prévia da tela final do app) ─────────
    public function calcular(Request $request, CalculadoraFormulacao $calculadora)
    {
        $validator = Validator::make($request->all(), [
            'exig_cms_kg' => 'required|numeric|gt:0',
            ...$this->regrasIngredientes(),
        ]);
        $this->validarSomaProporcoes($validator);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $calculo = $calculadora->calcular(
            (float) $request->exig_cms_kg,
            $this->montarItens($request->ingredientes),
        );

        return response()->json($calculo);
    }

    // ── Adiciona ou atualiza ingredientes do programa ─────────────────────────

    public function salvarIngredientes(Request $request, $id, CalculadoraFormulacao $calculadora)
    {
        $user = auth()->user();

        $programa = ProgramaRacao::where('criado_por', $user->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), $this->regrasIngredientes());
        $this->validarSomaProporcoes($validator);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $calculo = $calculadora->calcular(
            (float) ($programa->exig_cms_kg ?? 0),
            $this->montarItens($request->ingredientes),
        );

        DB::transaction(function () use ($programa, $calculo) {
            // Remove ingredientes anteriores
            RacaoIngrediente::where('programa_id', $programa->id)->delete();

            foreach ($calculo['itens'] as $ordem => $linha) {
                RacaoIngrediente::create([
                    'programa_id' => $programa->id,
                    'ordem'       => $ordem + 1,
                    ...collect($linha)->except(['nome', 'ms_pct'])->all(),
                ]);
            }

            $programa->update([
                'custo_animal_dia' => $calculo['totais']['custo_animal_dia'],
                'status'           => 'ativo',
            ]);
        });

        return response()->json([
            'message'  => 'Ingredientes salvos com sucesso.',
            'programa' => $programa->fresh()->load(['ingredientes.ingrediente']),
        ]);
    }

    /** Regras dos ingredientes, iguais na prévia e no salvamento. */
    private function regrasIngredientes(): array
    {
        return [
            'ingredientes'                  => 'required|array|min:1',
            'ingredientes.*.ingrediente_id' => 'required|uuid|distinct|exists:ingredientes,id',
            'ingredientes.*.tipo'           => 'required|in:volumoso_principal,volumoso_suplementar,concentrado',
            'ingredientes.*.proporcao_pct'  => 'required|numeric|min:0|max:100',
            'ingredientes.*.preco_kg_local' => 'nullable|numeric|min:0',
        ];
    }

    /** A soma das proporções precisa fechar 100% (tolerância de arredondamento). */
    private function validarSomaProporcoes(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $ingredientes = $v->getData()['ingredientes'] ?? null;
            if (!is_array($ingredientes) || $v->errors()->has('ingredientes*')) {
                return;
            }

            $soma = collect($ingredientes)->sum(fn ($i) => (float) ($i['proporcao_pct'] ?? 0));
            if (abs($soma - 100) > 0.01) {
                $v->errors()->add('ingredientes', 'A soma das proporções deve ser 100%. Atual: ' . number_format($soma, 2, ',', '.') . '%.');
            }
        });
    }

    /**
     * Junta cada item da requisição ao seu ingrediente do banco (uma única consulta),
     * mantendo a ordem enviada pelo app.
     */
    private function montarItens(array $ingredientes): array
    {
        $doBanco = Ingrediente::whereIn('id', collect($ingredientes)->pluck('ingrediente_id'))
            ->get()
            ->keyBy('id');

        return collect($ingredientes)->map(fn ($item) => [
            'ingrediente'    => $doBanco[$item['ingrediente_id']],
            'tipo'           => $item['tipo'],
            'proporcao_pct'  => $item['proporcao_pct'],
            'preco_kg_local' => $item['preco_kg_local'] ?? null,
        ])->all();
    }

    // ── Encerra um programa ───────────────────────────────────────────────────

    public function encerrar($id)
    {
        $user = auth()->user();

        $programa = ProgramaRacao::where('criado_por', $user->id)
            ->findOrFail($id);

        $programa->update(['status' => 'encerrado']);

        return response()->json([
            'message'  => 'Programa encerrado com sucesso.',
            'programa' => $programa,
        ]);
    }

    // ── Gera um PDF ───────────────────────────────────────────────────
    public function pdf($id)
    {
        $user = auth()->user();

        $programa = ProgramaRacao::where('criado_por', $user->id)
            ->with([
                'especie', 'raca', 'categoria', 'sistema',
                'ingredientes.ingrediente',
                'contrato.fazenda', 'contrato.fazendeiro',
                'criador',
            ])
            ->findOrFail($id);

        $html = view('pdf.formulacao-racao', compact('programa'))->render();

        return response()->json([
            'html'     => $html,
            'programa' => $programa,
        ]);
    }
}
