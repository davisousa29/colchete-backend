<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O cliente no gateway (Asaas) representa a pessoa, não a assinatura:
 * quem ainda não tem assinatura também precisa ser cliente para pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gateway_customer_id')->nullable();
        });

        // Leva os clientes já criados para o usuário (um por usuário)
        DB::statement('
            UPDATE users
               SET gateway_customer_id = s.gateway_customer_id
              FROM subscriptions s
             WHERE s.user_id = users.id
               AND s.gateway_customer_id IS NOT NULL
        ');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('gateway_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gateway_customer_id')->nullable();
        });

        DB::statement('
            UPDATE subscriptions
               SET gateway_customer_id = u.gateway_customer_id
              FROM users u
             WHERE u.id = subscriptions.user_id
               AND u.gateway_customer_id IS NOT NULL
        ');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gateway_customer_id');
        });
    }
};
