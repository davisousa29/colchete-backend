<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('data_nascimento')->nullable();
        });

        // Contas criadas antes da confirmação de e-mail contam como confirmadas
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);

        Schema::create('cadastros_pendentes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->text('dados');                          // criptografado (nome, CPF, senha em hash...)
            $table->string('codigo');                       // hash dos 6 dígitos
            $table->timestamp('expira_em');
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->timestamp('ultimo_envio_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadastros_pendentes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('data_nascimento');
        });
    }
};
