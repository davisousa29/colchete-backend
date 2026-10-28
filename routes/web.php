<?php

use App\Http\Controllers\Web\TermoPublicoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── Páginas públicas dos termos (links das lojas e do app) ─────────────
Route::get('/termos', [TermoPublicoController::class, 'show'])->defaults('tipo', 'uso');
Route::get('/privacidade', [TermoPublicoController::class, 'show'])->defaults('tipo', 'privacidade');
