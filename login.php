<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('index.php');

$erro = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = do_login((string)($_POST['email'] ?? ''), (string)($_POST['senha'] ?? ''));
    if ($u) {
        if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            header('Location: ' . $next);
            exit;
        }
        redirect('index.php');
    }
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/papiro-v2.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">📜 <b>Papiro Máximo</b></div>
  <h2 style="margin-top:0">Entrar</h2>
  <?php foreach (flashes() as $f): ?><div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endforeach; ?>
  <?php if ($erro): ?><div class="flash error"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label>E-mail <input type="email" name="email" required autofocus value="<?= e((string)($_POST['email'] ?? '')) ?>"></label>
    <label>Senha <input type="password" name="senha" required></label>
    <button class="btn btn-gold btn-block" type="submit">Entrar →</button>
  </form>
  <p class="muted auth-help">Para trocar sua senha, entre na conta e use <b>Meu perfil → Alterar senha</b>.</p>
  <p class="muted">Ainda não tem conta? <a href="<?= e(url('cadastro.php')) ?>">Criar conta grátis</a></p>
</div>
</body>
</html>
