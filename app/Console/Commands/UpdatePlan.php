<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePlan extends Command
{
    protected $signature = 'plans:update
        {slug? : Slug do plano (monthly, semiannual, annual). Sem slug, só lista}
        {--price= : Novo preço, ex.: 24.90}
        {--days= : Novos dias de acesso}
        {--name= : Novo nome exibido no app}
        {--activate : Ativa o plano}
        {--deactivate : Desativa o plano (some do app)}';

    protected $description = 'Lista os planos ou altera preço, dias, nome e status de um plano';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        if (!$slug) {
            $this->listPlans();
            return self::SUCCESS;
        }

        $plan = Plan::where('slug', $slug)->first();

        if (!$plan) {
            $this->error("Plano \"{$slug}\" não encontrado.");
            $this->listPlans();
            return self::FAILURE;
        }

        if ($this->option('activate') && $this->option('deactivate')) {
            $this->error('Use --activate ou --deactivate, não os dois.');
            return self::FAILURE;
        }

        $novos = [];

        if (($price = $this->option('price')) !== null) {
            $price = str_replace(',', '.', $price);
            if (!is_numeric($price) || (float) $price <= 0) {
                $this->error('Preço inválido. Exemplo: --price=24.90');
                return self::FAILURE;
            }
            $novos['price'] = round((float) $price, 2);
        }

        if (($days = $this->option('days')) !== null) {
            if (!ctype_digit((string) $days) || (int) $days <= 0) {
                $this->error('Dias inválidos. Exemplo: --days=30');
                return self::FAILURE;
            }
            $novos['days'] = (int) $days;
        }

        if (($name = $this->option('name')) !== null) {
            if (trim($name) === '') {
                $this->error('O nome não pode ser vazio.');
                return self::FAILURE;
            }
            $novos['name'] = trim($name);
        }

        if ($this->option('activate')) {
            $novos['is_active'] = true;
        }
        if ($this->option('deactivate')) {
            $novos['is_active'] = false;
        }

        // Mantém só o que realmente muda
        $mudancas = array_filter(
            $novos,
            fn ($valor, $campo) => $this->differs($plan->{$campo}, $valor),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($mudancas)) {
            $this->info('Nada para alterar.');
            return self::SUCCESS;
        }

        $this->table(
            ['Campo', 'Atual', 'Novo'],
            collect($mudancas)->map(fn ($valor, $campo) => [
                $campo,
                $this->show($campo, $plan->{$campo}),
                $this->show($campo, $valor),
            ])->values()->all()
        );

        $this->warn('Cobranças já geradas mantêm o valor antigo; só as novas usam este.');

        if (!$this->confirm("Aplicar no plano \"{$plan->name}\"?")) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $antes = $plan->only(array_keys($mudancas));
        $plan->update($mudancas);

        Log::info('Plano alterado via plans:update', [
            'slug'   => $plan->slug,
            'antes'  => $antes,
            'depois' => $mudancas,
        ]);

        $this->info('Plano atualizado.');
        $this->listPlans();

        return self::SUCCESS;
    }

    private function listPlans(): void
    {
        $this->table(
            ['Slug', 'Nome', 'Preço', 'Dias', 'Ativo', 'Ordem'],
            Plan::orderBy('sort_order')->get()->map(fn (Plan $p) => [
                $p->slug,
                $p->name,
                'R$ ' . number_format((float) $p->price, 2, ',', '.'),
                $p->days,
                $p->is_active ? 'sim' : 'não',
                $p->sort_order,
            ])->all()
        );
    }

    private function differs(mixed $atual, mixed $novo): bool
    {
        if (is_float($novo)) {
            return round((float) $atual, 2) !== $novo;
        }
        if (is_bool($novo)) {
            return (bool) $atual !== $novo;
        }
        return $atual != $novo;
    }

    private function show(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'price'     => 'R$ ' . number_format((float) $valor, 2, ',', '.'),
            'is_active' => $valor ? 'sim' : 'não',
            default     => (string) $valor,
        };
    }
}
