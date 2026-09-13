<?php
declare(strict_types=1);
/* Papiro Máximo — bootstrap carregado por todas as páginas */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Se o banco ainda não foi instalado, manda para o instalador.
$__self = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($__self !== 'install.php') {
    try {
        db()->query('SELECT id FROM users LIMIT 1');
    } catch (Throwable $e) {
        if ($__self === 'api.php') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'Banco não instalado. Acesse install.php']);
            exit;
        }
        header('Location: ' . url('install.php'));
        exit;
    }
}
unset($__self);
