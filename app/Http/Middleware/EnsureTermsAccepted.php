<?php

namespace App\Http\Middleware;

use App\Services\TermosService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function __construct(private TermosService $termos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->termos->possuiPendencias($user)) {
            return response()->json([
                'message' => 'Para continuar, aceite os termos atualizados.',
                'code'    => 'termos_pendentes',
            ], 403);
        }

        return $next($request);
    }
}
