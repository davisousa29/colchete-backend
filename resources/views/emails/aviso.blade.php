<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#f4f6f5; font-family: Arial, sans-serif;">
<div style="max-width:480px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

    <div style="background-color:#2E5E43; padding:24px; text-align:center;">
        <h1 style="color:#ffffff; margin:0; font-size:20px;">{{ $titulo }}</h1>
    </div>

    <div style="padding:32px 24px;">
        <p style="color:#333333; font-size:15px; line-height:1.5; margin:0 0 16px;">
            Olá, {{ $nome }}.
        </p>

        <p style="color:#333333; font-size:15px; line-height:1.5; margin:0 0 20px;">
            {{ $mensagem }}
        </p>

        @if ($botaoTexto && $botaoUrl)
            {{-- Botão em tabela: é o formato que funciona em todos os leitores de e-mail --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto 12px;">
                <tr>
                    <td style="background-color:#2D6A4F; border-radius:10px;">
                        <a href="{{ $botaoUrl }}" target="_blank"
                           style="display:inline-block; padding:14px 28px; color:#ffffff; font-size:15px; font-weight:bold; text-decoration:none;">
                            {{ $botaoTexto }}
                        </a>
                    </td>
                </tr>
            </table>

            {{-- O endereço também em texto, para quem não vê o botão --}}
            <p style="color:#777777; font-size:12px; line-height:1.5; margin:0 0 20px; text-align:center; word-break:break-all;">
                {{ $botaoUrl }}
            </p>
        @endif

        @if ($observacao)
            <p style="color:#777777; font-size:13px; line-height:1.5; margin:0;">
                {{ $observacao }}
            </p>
        @endif
    </div>

    <div style="background-color:#fafafa; padding:16px 24px; text-align:center;">
        <p style="color:#999999; font-size:12px; margin:0;">
            Dúvidas? Escreva para {{ $emailSuporte }}
        </p>
    </div>

</div>
</body>
</html>
