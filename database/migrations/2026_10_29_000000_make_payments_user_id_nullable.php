<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos sobrevivem à exclusão da conta, anonimizados:
 * ao apagar o usuário, o user_id vira NULL em vez de apagar o pagamento junto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Atenção: só reverte se não houver pagamentos anonimizados (user_id NULL)
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
