<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$erro = '';
$info = '';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$pdo = db();

// Troca de senha com token válido
$tokenRow = null;
if ($token !== '') {
    $st = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND usado = 0 AND expira_em > ?');
    $st->execute([$token, now_str()]);
    $tokenRow = $st->fetch() ?: null;
    if (!$tokenRow) $erro = 'Link inválido ou expirado. Solicite um novo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro === '') {
    if ($tokenRow) {
        $nova = (string)($_POST['nova'] ?? '');
        if (strlen($nova) < 6) $erro = 'A nova senha deve ter ao menos 6 caracteres.';
        else {
            $pdo->prepare('UPDATE users SET senha_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $tokenRow['user_id']]);
            $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE id = ?')->execute([$tokenRow['id']]);
            flash('success', 'Senha alterada! Entre com a nova senha.');
            redirect('login.php');
        }
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u) {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$u['id']]);
            $tk = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO password_resets (user_id, token, expira_em) VALUES (?,?,?)')
                ->execute([$u['id'], $tk, date('Y-m-d H:i:s', strtotime('+1 hour'))]);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('recuperar.php?token=' . $tk);
            $info = 'Link gerado (válido por 1h): ' . $link;
        } else {
            // resposta genérica (não revela se o e-mail existe)
            $info = 'Se este e-mail estiver cadastrado, um link de recuperação foi gerado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar senha · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">📜 <b>Papiro Máximo</b></div>
  <h2 style="margin-top:0">Recuperar senha</h2>
  <?php if ($erro): ?><div class="flash error"><?= e($erro) ?></div><?php endif; ?>
  <?php if ($info): ?><div class="flash info" style="word-break:break-all"><?= e($info) ?></div><?php endif; ?>
  <?php if ($tokenRow): ?>
    <form method="post" class="form">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Nova senha <input type="password" name="nova" required></label>
      <button class="btn btn-gold btn-block" type="submit">Alterar senha</button>
    </form>
  <?php elseif (!$info): ?>
    <form method="post" class="form">
      <label>Seu e-mail cadastrado <input type="email" name="email" required></label>
      <button class="btn btn-gold btn-block" type="submit">Gerar link</button>
    </form>
    <p class="muted">Demonstração sem SMTP: o link aparece na tela. Em produção, envie por e-mail.</p>
  <?php endif; ?>
  <p class="muted"><a href="<?= e(url('login.php')) ?>">← Voltar ao login</a></p>
</div>
</body>
</html>
