<?php

namespace App\Exceptions;

// Erro de cadastro com mensagem para o usuário, status HTTP e dados extras opcionais
class CadastroException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}
