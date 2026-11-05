<?php

// Configurações próprias do Colchete (fora das do Laravel)
return [

    // Endereço do site do consultor: links dos e-mails (renovar, recuperar senha...)
    // Em desenvolvimento, aponte para o ng serve: WEB_URL=http://localhost:4200
    'web_url' => rtrim(env('WEB_URL', 'https://consultor.colcheteapp.com.br'), '/'),

    // Contato de suporte mostrado aos usuários (e-mails, telas)
    'email_suporte' => env('EMAIL_SUPORTE', 'colchete.gestaorural@gmail.com'),

];
