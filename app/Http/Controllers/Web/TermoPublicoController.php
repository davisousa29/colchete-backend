<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TermosService;
use Illuminate\Support\Str;

class TermoPublicoController extends Controller
{
    public function show(string $tipo, TermosService $termos)
    {
        $termo = $termos->vigentes()->get($tipo);

        abort_if(!$termo, 404);

        return view('termos.publico', [
            'termo' => $termo,
            // Markdown → HTML, descartando qualquer HTML embutido no texto
            'html'  => Str::markdown($termo->conteudo, [
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
