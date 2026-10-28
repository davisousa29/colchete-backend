<?php

namespace App\Console\Commands;

use App\Models\Notificacao;
use App\Models\Termo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublicarTermo extends Command
{
    protected $signature = 'termos:publicar
        {tipo : uso ou privacidade}
        {arquivo : Caminho do .md a partir da raiz do projeto (ex.: resources/termos/uso.md)}
        {--resumo= : O que mudou nesta versão (aparece no aviso aos usuários)}
        {--sem-novo-aceite : Correção pequena: passa a exibir a versão nova sem pedir novo aceite}';

    protected $description = 'Publica uma nova versão dos Termos de Uso ou da Política de Privacidade';

    public function handle(): int
    {
        $tipo = $this->argument('tipo');

        if (!array_key_exists($tipo, Termo::TIPOS)) {
            $this->error('Tipo inválido. Use: ' . implode(' ou ', array_keys(Termo::TIPOS)) . '.');
            return self::FAILURE;
        }

        $caminho = base_path($this->argument('arquivo'));

        if (!is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");
            return self::FAILURE;
        }

        $conteudo = trim(file_get_contents($caminho));

        if ($conteudo === '') {
            $this->error('O arquivo está vazio.');
            return self::FAILURE;
        }

        $atual  = Termo::where('tipo', $tipo)->orderByDesc('versao')->first();
        $exige  = !$this->option('sem-novo-aceite');
        $resumo = $this->option('resumo');

        if ($atual && $atual->conteudo === $conteudo) {
            $this->warn('O texto é igual ao da versão vigente. Nada foi publicado.');
            return self::SUCCESS;
        }

        if (!$atual && !$exige) {
            $this->error('A primeira versão sempre exige aceite.');
            return self::FAILURE;
        }

        if ($atual && $exige && blank($resumo)) {
            $this->error('Informe --resumo="..." para os usuários saberem o que mudou.');
            return self::FAILURE;
        }

        $versao = ($atual?->versao ?? 0) + 1;

        $this->table(['Campo', 'Valor'], [
            ['Documento', Termo::TIPOS[$tipo]],
            ['Versão', ($atual ? "v{$atual->versao} → " : '') . "v{$versao}"],
            ['Exige novo aceite', $exige ? 'sim' : 'não'],
            ['Resumo', $resumo ?: '—'],
            ['Tamanho do texto', mb_strlen($conteudo) . ' caracteres'],
        ]);

        if ($atual && $exige) {
            $this->warn('Todos os usuários serão avisados e precisarão aceitar de novo para continuar usando o app.');
        }

        if (!$this->confirm('Publicar?')) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $termo = Termo::create([
            'tipo'              => $tipo,
            'versao'            => $versao,
            'titulo'            => Termo::TIPOS[$tipo],
            'conteudo'          => $conteudo,
            'resumo_alteracoes' => $resumo,
            'exige_novo_aceite' => $exige,
            'publicado_em'      => now(),
        ]);

        Log::info('Termo publicado via termos:publicar', [
            'tipo'              => $tipo,
            'versao'            => $versao,
            'exige_novo_aceite' => $exige,
        ]);

        $this->info("{$termo->titulo} v{$versao} publicado.");

        // Na primeira versão não há "atualização" a avisar
        if ($atual && $exige) {
            $avisados = $this->notificarUsuarios($termo);
            $this->info("Usuários avisados: {$avisados}.");
        }

        return self::SUCCESS;
    }

    private function notificarUsuarios(Termo $termo): int
    {
        $total = 0;

        User::where('active', true)
            ->select('id')
            ->chunkById(200, function ($users) use ($termo, &$total) {
                foreach ($users as $user) {
                    Notificacao::create([
                        'user_id'  => $user->id,
                        'tipo'     => 'termos',
                        'titulo'   => "Atualizamos nossa {$termo->titulo}",
                        'mensagem' => $termo->resumo_alteracoes,
                        'dados'    => ['termo_id' => $termo->id, 'versao' => $termo->versao],
                        'lida'     => false,
                    ]);
                    $total++;
                }
            });

        return $total;
    }
}
