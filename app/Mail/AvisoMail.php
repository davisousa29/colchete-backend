<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de aviso genérico: título, mensagem e, se fizer sentido, um botão de ação.
 *
 * Usado pelos avisos de assinatura (com botão para a conta no site), pelo aviso
 * de senha alterada e pela confirmação de exclusão de conta.
 */
class AvisoMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSuporte;

    public function __construct(
        public string $nome,
        public string $titulo,
        public string $mensagem,
        public ?string $botaoTexto = null,
        public ?string $botaoUrl = null,
        public ?string $observacao = null,
    ) {
        $this->emailSuporte = config('colchete.email_suporte');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.aviso',
        );
    }
}
