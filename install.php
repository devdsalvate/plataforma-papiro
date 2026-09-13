<?php
declare(strict_types=1);
/* ============================================================
   PAPIRO MÁXIMO — Instalador web
   Acesse http://localhost/papiro-maximo/install.php
   APAGUE este arquivo após instalar em produção.
   ============================================================ */
require __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('e')) {
    function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$lockFile = __DIR__ . '/database/installed.lock';
$erro = '';
$sucesso = null;

// ---------- checks ----------
$checks = [
    ['PHP 8.0 ou superior', version_compare(PHP_VERSION, '8.0.0', '>=') . '' === '1' || version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['Extensão PDO MySQL (produção/XAMPP)', extension_loaded('pdo_mysql')],
    ['Extensão PDO SQLite (demonstração)', extension_loaded('pdo_sqlite')],
    ['Extensão mbstring (textos UTF-8)', extension_loaded('mbstring')],
    ['Extensão curl (IA Groq)', extension_loaded('curl')],
    ['Pasta includes/ gravável', is_writable(__DIR__ . '/includes')],
    ['Pasta database/ gravável', is_writable(__DIR__ . '/database')],
    ['Uploads de imagens (cria automaticamente)', is_writable(__DIR__ . '/assets') || !is_dir(__DIR__ . '/assets')],
];

function pm_split_sql(string $sql): array {
    $lines = explode("\n", $sql);
    $clean = [];
    foreach ($lines as $ln) {
        if (preg_match('/^\s*--/', $ln)) continue;
        $clean[] = $ln;
    }
    $parts = preg_split('/;\s*\n/', implode("\n", $clean));
    return array_values(array_filter(array_map('trim', $parts ?: [])));
}

$TABLES = ['ia_perguntas','videoaulas','grupo_membros','grupos','guia_artigos','trilha_progresso','trilha_modulos','trilhas','study_sessions','comentarios','caderno_erros','favoritos','respostas','questoes','password_resets','users'];

// ---------- POST: instalar ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver = ($_POST['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
    $reinst = isset($_POST['reinstall']) && is_file($lockFile);
    if (is_file($lockFile) && !$reinst) $erro = 'O sistema já está instalado. Marque "Reinstalar" para refazer (apaga tudo).';

    $admin_nome = trim((string)($_POST['admin_nome'] ?? ''));
    $admin_email = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $admin_senha = (string)($_POST['admin_senha'] ?? '');
    if ($erro === '' && ($admin_nome === '' || !filter_var($admin_email, FILTER_VALIDATE_EMAIL) || strlen($admin_senha) < 6)) {
        $erro = 'Informe nome, e-mail válido e senha do admin (mín. 6 caracteres).';
    }

    if ($erro === '') {
        try {
            if ($driver === 'sqlite') {
                if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('PDO SQLite não disponível.');
                $sqlitePath = APP_ROOT . '/database/papiro.sqlite';
                if ($reinst && is_file($sqlitePath)) unlink($sqlitePath);
                $pdo = new PDO('sqlite:' . $sqlitePath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $local = ['DB_DRIVER' => 'sqlite'];
            } else {
                if (!extension_loaded('pdo_mysql')) throw new RuntimeException('PDO MySQL não disponível.');
                $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
                $name = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['db_name'] ?? 'papiro_maximo')) ?: 'papiro_maximo';
                $dbuser = trim((string)($_POST['db_user'] ?? 'root'));
                $dbpass = (string)($_POST['db_pass'] ?? '');
                $pdo0 = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo0->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $local = ['DB_DRIVER' => 'mysql', 'DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $dbuser, 'DB_PASS' => $dbpass];
            }
            $gem = trim((string)($_POST['gemini_key'] ?? ''));
            if ($gem !== '') $local['GEMINI_API_KEY'] = $gem;
            $mis = trim((string)($_POST['mistral_key'] ?? ''));
            if ($mis !== '') $local['MISTRAL_API_KEY'] = $mis;
            $groq = trim((string)($_POST['groq_key'] ?? ''));
            if ($groq !== '') $local['GROQ_API_KEY'] = $groq;
            file_put_contents(__DIR__ . '/includes/config.local.php', "<?php\n// Gerado pelo instalador em " . date('Y-m-d H:i:s') . "\nreturn " . var_export($local, true) . ";\n");

            if ($reinst && $driver === 'mysql') {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($TABLES as $t) $pdo->exec("DROP TABLE IF EXISTS `$t`");
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }

            // schema
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            if ($driver === 'sqlite') {
                $schema = str_replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $schema);
            }
            foreach (pm_split_sql($schema) as $stmt) $pdo->exec($stmt);

            // seed
            $seed = require __DIR__ . '/database/seed.php';
            $stQ = $pdo->prepare('INSERT INTO questoes (slug,concurso,ano,materia,assunto,dificuldade,enunciado,alt_a,alt_b,alt_c,alt_d,alt_e,gabarito,resolucao) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($seed['questoes'] as $q) {
                $stQ->execute([$q['slug'], $q['concurso'], $q['ano'], $q['materia'], $q['assunto'], $q['dificuldade'], $q['enunciado'], $q['alternativas'][0], $q['alternativas'][1], $q['alternativas'][2], $q['alternativas'][3], $q['alternativas'][4], $q['gabarito'], $q['resolucao']]);
            }
            $stT = $pdo->prepare('INSERT INTO trilhas (slug,nome,icone,cor) VALUES (?,?,?,?)');
            $stM = $pdo->prepare('INSERT INTO trilha_modulos (trilha_id,titulo,ordem) VALUES (?,?,?)');
            foreach ($seed['trilhas'] as $t) {
                $stT->execute([$t['slug'], $t['nome'], $t['icone'], $t['cor']]);
                $tid = (int)$pdo->lastInsertId();
                $ord = 1;
                foreach ($t['modulos'] as $m) $stM->execute([$tid, $m, $ord++]);
            }
            $stG = $pdo->prepare('INSERT INTO guia_artigos (slug,titulo,icone,tempo,texto) VALUES (?,?,?,?,?)');
            foreach ($seed['guia'] as $g) $stG->execute([$g['slug'], $g['titulo'], $g['icone'], $g['tempo'], $g['texto']]);
            $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute(['Como começar na EFOMM: plano de 90 dias', 'https://www.youtube.com/', 'EFOMM', 'Geral', 'Visão geral da prova, pesos e cronograma sugerido.']);
            $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute(['EPCAR Matemática: os 10 temas que mais caem', 'https://www.youtube.com/', 'EPCAR', 'Matemática', 'Análise dos assuntos campeões de cobrança.']);
            $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute(['Física para EEAR do zero', 'https://www.youtube.com/', 'EEAR', 'Física', 'Cinemática e dinâmica com questões comentadas.']);
            $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute(['Colégio Naval: geometria plana essencial', 'https://www.youtube.com/', 'CN', 'Matemática', 'Teoremas e truques de construção.']);
            $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute(['ITA: como estudar por provas antigas', 'https://www.youtube.com/', 'ITA', 'Geral', 'Método de engenharia reversa da banca.']);

            // admin
            $pdo->prepare('INSERT INTO users (nome,email,senha_hash,foco,role) VALUES (?,?,?,?,?)')->execute([$admin_nome, $admin_email, password_hash($admin_senha, PASSWORD_DEFAULT), 'EFOMM', 'admin']);

            file_put_contents($lockFile, 'Instalado em ' . date('Y-m-d H:i:s') . "\n");
            $sucesso = ['email' => $admin_email, 'n' => count($seed['questoes'])];
        } catch (Throwable $e) {
            $erro = 'Falha na instalação: ' . $e->getMessage();
        }
    }
}

$instalado = is_file($lockFile) && $sucesso === null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação · Papiro Máximo</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:640px">
  <div class="auth-brand">📜 <b>Papiro Máximo</b> · Instalador</div>

  <h3>1. Requisitos</h3>
  <ul class="check-list">
    <?php foreach ($checks as [$label, $pass]): ?>
      <li class="<?= $pass ? 'ok' : 'fail' ?>"><?= $pass ? '✅' : '❌' ?> <?= htmlspecialchars($label) ?></li>
    <?php endforeach; ?>
    <li class="ok">🔧 PHP atual: <?= e(PHP_VERSION) ?></li>
  </ul>

  <?php if ($erro !== ''): ?><div class="flash error"><?= e($erro) ?></div><?php endif; ?>

  <?php if ($sucesso): ?>
    <div class="flash success">✅ Instalação concluída com <?= (int)$sucesso['n'] ?> questões! Admin: <b><?= e($sucesso['email']) ?></b></div>
    <p>⚠️ <b>Apague o arquivo <code>install.php</code></b> antes de usar em produção.</p>
    <p><a class="btn btn-gold" href="<?= e(url('login.php')) ?>">Ir para o login →</a></p>
  <?php elseif ($instalado): ?>
    <div class="flash info">O Papiro Máximo já está instalado.</div>
    <p><a class="btn btn-gold" href="<?= e(url('index.php')) ?>">Abrir a plataforma →</a></p>
    <details>
      <summary>Reinstalar (apaga TODOS os dados)</summary>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="driver" value="mysql">
        <input type="hidden" name="db_host" value="127.0.0.1">
        <input type="hidden" name="db_name" value="papiro_maximo">
        <input type="hidden" name="db_user" value="root">
        <input type="hidden" name="admin_nome" value="Admin">
        <input type="hidden" name="admin_email" value="admin@papiro.local">
        <input type="hidden" name="admin_senha" value="admin123">
        <label><input type="checkbox" name="reinstall" value="1" required> Confirmo que quero apagar tudo e reinstalar (usa o banco atual do config.local.php quando SQLite).</label>
        <p style="margin-top:8px"><button class="btn btn-danger" type="submit">Reinstalar</button> <span class="muted">Para MySQL, edite os campos ocultos ou apague installed.lock + config.local.php e recarregue.</span></p>
      </form>
    </details>
  <?php else: ?>
    <h3>2. Banco de dados + Admin</h3>
    <form method="post" class="form">
      <label>Driver
        <select name="driver" id="driverSel">
          <option value="mysql">MySQL/MariaDB (XAMPP / InfinityFree)</option>
          <option value="sqlite">SQLite (demonstração rápida)</option>
        </select>
      </label>
      <div id="mysqlFields" class="grid-2">
        <label>Host <input name="db_host" value="127.0.0.1"></label>
        <label>Banco <input name="db_name" value="papiro_maximo"></label>
        <label>Usuário <input name="db_user" value="root"></label>
        <label>Senha <input name="db_pass" type="password" value=""></label>
      </div>
      <div class="grid-2">
        <label>Nome do admin <input name="admin_nome" value="Admin" required></label>
        <label>E-mail do admin <input name="admin_email" type="email" value="admin@papiro.local" required></label>
      </div>
      <label>Senha do admin (mín. 6) <input name="admin_senha" type="password" value="admin123" required></label>
      <label>Gemini API Key <span class="muted">(IA principal, grátis — crie em aistudio.google.com/apikey)</span> <input name="gemini_key" placeholder="AIza..."></label>
      <label>Mistral API Key <span class="muted">(2ª reserva, grátis sem cartão — console.mistral.ai, ative o plano Experiment)</span> <input name="mistral_key" placeholder="..."></label>
      <label>Groq API Key <span class="muted">(opcional — reserva automática; pode configurar depois)</span> <input name="groq_key" placeholder="gsk_..."></label>
      <button class="btn btn-gold" type="submit">🚀 Instalar agora</button>
    </form>
    <script>
      document.getElementById('driverSel').addEventListener('change', function () {
        document.getElementById('mysqlFields').style.display = this.value === 'mysql' ? '' : 'none';
      });
    </script>
  <?php endif; ?>
</div>
</body>
</html>
