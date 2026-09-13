<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('index.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $foco = (string)($_POST['foco'] ?? 'EFOMM');
    if (!in_array($foco, CONCURSOS_TODOS, true)) $foco = 'EFOMM';

    if (mb_strlen($nome) < 2) $erro = 'Informe seu nome.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
    elseif (strlen($senha) < 6) $erro = 'A senha deve ter ao menos 6 caracteres.';
    else {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) $erro = 'Este e-mail já está cadastrado. Tente entrar.';
        else {
            db()->prepare('INSERT INTO users (nome, email, senha_hash, foco) VALUES (?,?,?,?)')
                ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $foco]);
            do_login($email, $senha);
            flash('success', 'Bem-vindo(a) ao Papiro Máximo, ' . explode(' ', $nome)[0] . '! 🎖️');
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criar conta · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">📜 <b>Papiro Máximo</b></div>
  <h2 style="margin-top:0">Criar conta grátis</h2>
  <?php if ($erro): ?><div class="flash error"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="form">
    <label>Nome completo <input name="nome" required value="<?= e((string)($_POST['nome'] ?? '')) ?>"></label>
    <label>E-mail <input type="email" name="email" required value="<?= e((string)($_POST['email'] ?? '')) ?>"></label>
    <label>Senha (mín. 6 caracteres) <input type="password" name="senha" required></label>
    <label>Seu concurso foco
      <select name="foco">
        <?php foreach (CONCURSOS_TODOS as $c): ?>
          <option value="<?= e($c) ?>" <?= (($_POST['foco'] ?? 'EFOMM') === $c) ? 'selected' : '' ?>><?= concurso_icone($c) ?> <?= e(concurso_nome($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-gold btn-block" type="submit">🚀 Criar minha conta</button>
  </form>
  <p class="muted">Já tem conta? <a href="<?= e(url('login.php')) ?>">Entrar</a></p>
</div>
</body>
</html>
