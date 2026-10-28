<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $termo->titulo }} — Colchete</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 760px; margin: 0 auto; padding: 32px 16px; line-height: 1.6; color: #1f2937; }
        h1, h2, h3 { line-height: 1.3; }
        .meta { color: #6b7280; font-size: 14px; margin-bottom: 32px; }
    </style>
</head>
<body>
<h1>{{ $termo->titulo }}</h1>
<p class="meta">
    Versão {{ $termo->versao }} · publicada em {{ $termo->publicado_em->format('d/m/Y') }}
</p>
{!! $html !!}
</body>
</html>
