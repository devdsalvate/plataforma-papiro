<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'dados') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $foco = (string)($_POST['foco'] ?? $user['foco']);
        if (!in_array($foco, CONCURSOS_TODOS, true)) $foco = $user['foco'];
        if (mb_strlen($nome) >= 2) {
            $pdo->prepare('UPDATE users SET nome = ?, foco = ? WHERE id = ?')->execute([$nome, $foco, $user['id']]);
            flash('success', 'Perfil atualizado! ✅');
            redirect('perfil.php');
        }
        flash('error', 'Nome inválido.');
    } elseif ($acao === 'senha') {
        $atual = (string)($_POST['atual'] ?? '');
        $nova = (string)($_POST['nova'] ?? '');
        if (!password_verify($atual, (string)$user['senha_hash'])) flash('error', 'Senha atual incorreta.');
        elseif (strlen($nova) < 6) flash('error', 'Nova senha deve ter ao menos 6 caracteres.');
        else {
            $pdo->prepare('UPDATE users SET senha_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Senha alterada! ✅');
            redirect('perfil.php');
        }
    }
    $user = current_user();
}

$stats = user_stats((int)$user['id']);
$title = 'Meu perfil';
$active = 'perfil';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>👤 Meu perfil</h1></div>

<div class="grid-stats">
  <div class="stat"><div class="stat-num"><?= e(fmt_duracao($stats['segundos'])) ?></div><div class="stat-label">⏱️ estudadas</div></div>
  <div class="stat"><div class="stat-num"><?= $stats['distintas'] ?></div><div class="stat-label">📝 questões</div></div>
  <div class="stat"><div class="stat-num"><?= $stats['taxa'] ?>%</div><div class="stat-label">🎯 acerto</div></div>
  <div class="stat gold"><div class="stat-num">🔥 <?= $stats['ofensiva'] ?></div><div class="stat-label">ofensiva</div></div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>📋 Meus dados</h3>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="dados">
      <label>Nome <input name="nome" value="<?= e($user['nome']) ?>" required></label>
      <label>E-mail <input value="<?= e($user['email']) ?>" disabled></label>
      <label>Concurso foco
        <select name="foco">
          <?php foreach (CONCURSOS_TODOS as $c): ?>
            <option value="<?= e($c) ?>" <?= $user['foco'] === $c ? 'selected' : '' ?>><?= concurso_icone($c) ?> <?= e(concurso_nome($c)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="muted">Membro desde <?= e(date('d/m/Y', strtotime($user['created_at']))) ?> · Perfil: <?= e($user['role']) ?></p>
      <button class="btn btn-gold" type="submit">Salvar</button>
    </form>
  </div>
  <div class="card">
    <h3>🔒 Alterar senha</h3>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="senha">
      <label>Senha atual <input type="password" name="atual" required></label>
      <label>Nova senha <input type="password" name="nova" required></label>
      <button class="btn" type="submit">Alterar senha</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
