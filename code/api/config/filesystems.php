<?php

/*
|--------------------------------------------------------------------------
| Discos de arquivo
|--------------------------------------------------------------------------
|
| Só o que este produto usa. Os demais discos de exemplo do Laravel ficaram de
| fora — disco configurado que ninguém usa é convite a usar por engano.
|
*/

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        | Os arquivos de exportação.
        |
        | ── Por que um disco só para isto ─────────────────────────────────
        |
        | Por causa das PERMISSÕES. O arquivo é escrito pelo worker da fila e lido pelo
        | PHP-FPM, que são processos de usuários DIFERENTES no mesmo host. Com o padrão do
        | Flysystem a pasta nasce 0700 e pertence a quem a criou — o FPM não consegue nem
        | entrar, `exists()` responde false, e a tela mostra "Pronto" para sempre sem oferecer
        | o download. Foi exatamente o que aconteceu na primeira exportação real.
        |
        | 0755/0644 não torna nada público na web: não há rota servindo esta pasta, e o
        | download passa pelo ExportController, que confere a conta. O que estes bits decidem
        | é só quais usuários DO CONTAINER conseguem ler — e são todos processos do mesmo app.
        */
        'exports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/exports'),
            'throw' => false,
            'report' => false,
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir' => ['public' => 0755, 'private' => 0755],
            ],
        ],

    ],

    /*
    | Links simbólicos criados por `storage:link`.
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
