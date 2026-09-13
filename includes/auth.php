<?php
declare(strict_types=1);
/* Papiro Máximo — Sessões, login, CSRF */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['user_id']]);
        $cache = $st->fetch() ?: false;
    }
    return $cache === false ? null : $cache;
}

function is_admin(?array $u = null): bool {
    $u = $u ?? current_user();
    return $u !== null && (($u['role'] ?? '') === 'admin');
}

/** @return array usuário logado (redireciona p/ login se não) */
function require_login(): array {
    $u = current_user();
    if (!$u) {
        $next = (string)($_SERVER['REQUEST_URI'] ?? '');
        redirect('login.php' . ($next !== '' ? '?next=' . urlencode($next) : ''));
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if (!is_admin($u)) {
        http_response_code(403);
        exit('Acesso negado: área administrativa.');
    }
    return $u;
}

function do_login(string $email, string $senha): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();
    if ($u && password_verify($senha, (string)$u['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        return $u;
    }
    return null;
}

function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'] ?? '/', $p['domain'] ?? '', (bool)($p['secure'] ?? false), (bool)($p['httponly'] ?? true));
    }
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf'];
}

function csrf_check(?string $t): bool {
    return isset($_SESSION['csrf']) && is_string($t) && $t !== '' && hash_equals((string)$_SESSION['csrf'], $t);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
