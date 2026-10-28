<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada linha é uma versão publicada — nunca é alterada depois
        Schema::create('termos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo', 20);                       // uso | privacidade
            $table->unsignedInteger('versao');
            $table->string('titulo');
            $table->longText('conteudo');                     // Markdown
            $table->text('resumo_alteracoes')->nullable();    // mostrado no aviso de atualização
            $table->boolean('exige_novo_aceite')->default(true);
            $table->timestamp('publicado_em');
            $table->timestamps();

            $table->unique(['tipo', 'versao']);
        });

        // Prova do aceite (LGPD): quem aceitou qual versão, quando e de onde
        Schema::create('aceites_termos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('termo_id')->constrained('termos')->restrictOnDelete();
            $table->timestamp('aceito_em');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'termo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aceites_termos');
        Schema::dropIfExists('termos');
    }
};
