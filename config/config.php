<?php
/**
 * Configurações do banco de dados.
 * Em produção, prefira carregar esses valores de variáveis de ambiente
 * (getenv) em vez de deixá-los fixos aqui.
 */

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'dbname'  => getenv('DB_NAME') ?: 'WSI',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];