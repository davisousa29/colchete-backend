<?php

namespace App\Console\Commands;

use App\Models\Configuracao;
use Illuminate\Console\Command;

class DefinirConfiguracao extends Command
{
    protected $signature = 'configuracoes:definir
                            {chave? : Ex.: suporte_whatsapp (sem informar, lista todas)}
                            {valor? : O novo valor}
                            {--limpar : Deixa a configuração vazia}';

    protected $description = 'Lista ou altera as configurações do sistema (tabela configuracoes)';

    public function handle(): int
    {
        $chave = $this->argument('chave');

        // Sem chave: só lista
        if (!$chave) {
            $this->table(['Chave', 'Valor', 'Descrição'],
                Configuracao::orderBy('chave')->get(['chave', 'valor', 'descricao'])->toArray());
            return self::SUCCESS;
        }

        $config = Configuracao::find($chave);
        if (!$config) {
            $this->error("Configuração \"{$chave}\" não existe.");
            return self::FAILURE;
        }

        $novo = $this->option('limpar') ? null : $this->argument('valor');
        if ($novo === null && !$this->option('limpar')) {
            $this->error('Informe o novo valor ou use --limpar.');
            return self::FAILURE;
        }

        // O link do WhatsApp (wa.me) só aceita números com DDI e DDD
        if ($chave === 'suporte_whatsapp' && $novo !== null && !preg_match('/^\d{12,13}$/', $novo)) {
            $this->error('Use só números, com DDI e DDD. Ex.: 5562999999999');
            return self::FAILURE;
        }

        $this->table(['Chave', 'Valor atual', 'Novo valor'], [[$chave, $config->valor ?? '(vazio)', $novo ?? '(vazio)']]);

        if (!$this->confirm('Confirmar a alteração?')) {
            $this->info('Nada foi alterado.');
            return self::SUCCESS;
        }

        $config->update(['valor' => $novo]);
        $this->info('Configuração atualizada.');

        return self::SUCCESS;
    }
}
