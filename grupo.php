<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$pdo = db();

$gid = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT g.*, u.nome AS dono_nome FROM grupos g JOIN users u ON u.id = g.dono_id WHERE g.id = ?');
$st->execute([$gid]);
$g = $st->fetch();
if (!$g) {
    flash('error', 'Grupo não encontrado.');
    redirect('ranking.php');
}

$st = $pdo->prepare('SELECT 1 FROM grupo_membros WHERE grupo_id = ? AND user_id = ?');
$st->execute([$gid, $uid]);
$membro = (bool)$st->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    if (($_POST['acao'] ?? '') === 'sair') {
        $pdo->prepare('DELETE FROM grupo_membros WHERE grupo_id = ? AND user_id = ?')->execute([$gid, $uid]);
        flash('info', 'Você saiu do grupo.');
        redirect('ranking.php');
    }
}

$desde = date('Y-m-01 00:00:00');
$ate = date('Y-m-01 00:00:00', strtotime('first day of next month'));
$rows = ranking_rows($desde, $ate, $gid, 50);

$title = $g['nome'];
$active = 'ranking';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <a class="btn btn-small" href="<?= e(url('ranking.php')) ?>">← Ranking</a>
  <h1>🛡️ <?= e($g['nome']) ?></h1>
</div>
<div class="card">
  <p class="muted"><?= e($g['descricao'] ?: 'Sem descrição.') ?> · Dono: <b><?= e($g['dono_nome']) ?></b> · Código: <code><?= e($g['codigo']) ?></code></p>
  <?php if ($membro): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Sair do grupo?')">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="sair">
      <button class="btn btn-small btn-danger" type="submit">Sair do grupo</button>
    </form>
  <?php else: ?>
    <p>Você não é membro deste grupo. Entre com o código <code><?= e($g['codigo']) ?></code> na <a href="<?= e(url('ranking.php')) ?>">página de ranking</a>.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>🏆 Ranking do grupo — mês atual</h3>
  <div class="table-wrap"><table class="table">
    <tr><th>#</th><th>Membro</th><th>Horas</th><th>Questões</th><th>🔥 Ofensiva</th></tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr <?= $r['id'] == $uid ? 'style="background:#fff4d6"' : '' ?>>
      <td><b><?= $i + 1 ?>º</b></td>
      <td><?= e($r['nome']) ?><?= $r['id'] == $uid ? ' (você)' : '' ?></td>
      <td><b><?= e(fmt_duracao((int)$r['seg'])) ?></b></td>
      <td><?= (int)$r['q'] ?></td>
      <td>🔥 <?= (int)$r['ofensiva'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
