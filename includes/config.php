<?php
declare(strict_types=1);
/* ============================================================
   PAPIRO MÁXIMO — Configuração central
   Ajuste aqui ou via variáveis de ambiente / config.local.php
   ============================================================ */

const APP_NAME = 'Papiro Máximo';
const APP_VERSION = '2.5.1';

date_default_timezone_set('America/Sao_Paulo');

define('APP_ROOT', str_replace('\\', '/', dirname(__DIR__)));

// Overrides locais: includes/config.local.php deve retornar um array.
// Ex.: <?php return ['DB_DRIVER'=>'sqlite','GROQ_API_KEY'=>'...'];
$PM_LOCAL = [];
$__localFile = __DIR__ . '/config.local.php';
if (is_file($__localFile)) {
    $tmp = require $__localFile;
    if (is_array($tmp)) $PM_LOCAL = $tmp;
}
unset($__localFile, $tmp);

function pm_cfg(string $key, $default = null) {
    global $PM_LOCAL;
    if (isset($PM_LOCAL[$key])) return $PM_LOCAL[$key];
    $env = getenv($key);
    if ($env !== false && $env !== '') return $env;
    return $default;
}

/** BASE_URL automática: '' na raiz do domínio, '/pasta' em subpasta (XAMPP). */
function pm_base_url(): string {
    $docroot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docroot !== '') {
        $doc = str_replace('\\', '/', (string)(realpath($docroot) ?: $docroot));
        $root = str_replace('\\', '/', (string)(realpath(APP_ROOT) ?: APP_ROOT));
        if ($doc !== '' && str_starts_with($root, $doc)) {
            return rtrim(substr($root, strlen($doc)), '/');
        }
    }
    return '';
}
define('BASE_URL', pm_base_url());

/** Monta URL interna respeitando subpasta. Ex.: url('questoes.php') */
function url(string $path = ''): string {
    $p = trim($path);
    if ($p === '' || $p === '/') return BASE_URL === '' ? '/' : BASE_URL . '/';
    return BASE_URL . '/' . ltrim($p, '/');
}

/* ---------- Banco de dados (padrões p/ XAMPP) ---------- */
define('DB_DRIVER', (string)pm_cfg('DB_DRIVER', 'mysql')); // mysql | sqlite
define('DB_HOST', (string)pm_cfg('DB_HOST', '127.0.0.1'));
define('DB_NAME', (string)pm_cfg('DB_NAME', 'papiro_maximo'));
define('DB_USER', (string)pm_cfg('DB_USER', 'root'));
define('DB_PASS', (string)pm_cfg('DB_PASS', ''));
define('DB_SQLITE_PATH', (string)pm_cfg('DB_SQLITE_PATH', APP_ROOT . '/database/papiro.sqlite'));

/* ---------- IA principal: Groq (rápida e já integrada ao projeto) ----------
   Salve em config.local.php: 'GROQ_API_KEY' => 'gsk_...'. */
define('GROQ_API_KEY', (string)pm_cfg('GROQ_API_KEY', ''));
define('GROQ_MODEL', (string)pm_cfg('GROQ_MODEL', 'openai/gpt-oss-120b'));
const GROQ_FALLBACKS = ['openai/gpt-oss-120b', 'openai/gpt-oss-20b'];

/* ---------- IA reserva: Google Gemini ----------
   Entra apenas quando a Groq estiver indisponível. */
define('GEMINI_API_KEY', (string)pm_cfg('GEMINI_API_KEY', ''));
define('GEMINI_MODEL', (string)pm_cfg('GEMINI_MODEL', 'gemini-2.5-flash'));
const GEMINI_FALLBACKS = ['gemini-2.5-flash', 'gemini-2.0-flash'];
