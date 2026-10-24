<?php

namespace App\Exceptions;

/**
 * Erro do checkout que a própria pessoa resolve (ex.: CPF faltando).
 * A mensagem é mostrada no app como está; qualquer outra exceção
 * continua virando a mensagem genérica.
 */
class CheckoutException extends \RuntimeException
{
}
