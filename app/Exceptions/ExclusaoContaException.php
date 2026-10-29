<?php

namespace App\Exceptions;

// Erro na exclusão de conta, com mensagem para o usuário e status HTTP
class ExclusaoContaException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
