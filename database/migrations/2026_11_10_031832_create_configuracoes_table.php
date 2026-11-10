<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave')->primary();
            $table->text('valor')->nullable();
            $table->string('descricao')->nullable();
            $table->timestamps();
        });

        $agora = now();
        DB::table('configuracoes')->insert([
            [
                'chave' => 'suporte_whatsapp',
                'valor' => null,
                'descricao' => 'WhatsApp do suporte, só números com DDI e DDD (ex.: 5562999999999)',
                'created_at' => $agora,
                'updated_at' => $agora
            ],
            [
                'chave' => 'suporte_email',
                'valor' => 'colchete.gestaorural@gmail.com',
                'descricao' => 'E-mail do suporte',
                'created_at' => $agora,
                'updated_at' => $agora
            ],
            [
                'chave' => 'suporte_mensagem',
                'valor' => 'Olá! Preciso de ajuda com o Colchete.',
                'descricao' => 'Mensagem que já vem escrita ao abrir o WhatsApp',
                'created_at' => $agora,
                'updated_at' => $agora
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
