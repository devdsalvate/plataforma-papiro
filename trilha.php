<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$uid = $user ? (int)$user['id'] : 0;
$pdo = db();

$slug = (string)($_GET['slug'] ?? '');
$st = $pdo->prepare('SELECT * FROM trilhas WHERE slug = ? AND ativo = 1');
$st->execute([$slug]);
$t = $st->fetch();
if (!$t) {
    $title = 'Trilha não encontrada';
    $active = 'trilhas';
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><p>😕 Trilha não encontrada. <a href="' . e(url('trilhas.php')) . '">Voltar</a></p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}
$st = $pdo->prepare('SELECT * FROM trilha_modulos WHERE trilha_id = ? ORDER BY ordem, id');
$st->execute([$t['id']]);
$modulos = $st->fetchAll();

$feitos = [];
if ($uid) {
    $st = $pdo->prepare('SELECT modulo_id FROM trilha_progresso p JOIN trilha_modulos m ON m.id = p.modulo_id WHERE p.user_id = ? AND m.trilha_id = ?');
    $st->execute([$uid, $t['id']]);
    $feitos = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
}
$total = count($modulos);
$pct = $total > 0 ? (int)round(count($feitos) * 100 / $total) : 0;

$title = $t['nome'];
$active = 'trilhas';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <a class="btn btn-small" href="<?= e(url('trilhas.php')) ?>">← Trilhas</a>
  <h1><?= e($t['icone'] . ' ' . $t['nome']) ?></h1>
</div>

<div class="card">
  <p><b>Seu progresso: <span id="trilhaPct"><?= $pct ?>%</span></b> (<?= count($feitos) ?>/<?= $total ?> módulos)</p>
  <div class="progress"><div class="progress-bar" id="trilhaBar" style="width:<?= $pct ?>%"></div></div>
  <?php if (!$uid): ?><p class="muted"><a href="<?= e(url('login.php')) ?>">Entre</a> para salvar seu progresso.</p><?php endif; ?>
</div>

<?php foreach ($modulos as $i => $m):
    $done = isset($feitos[$m['id']]); ?>
  <div class="modulo <?= $done ? 'done' : '' ?>">
    <input type="checkbox" data-modulo="<?= (int)$m['id'] ?>" <?= $done ? 'checked' : '' ?> <?= $uid ? '' : 'disabled' ?>>
    <div>
      <div class="m-title"><b>Módulo <?= $i + 1 ?>:</b> <?= e($m['titulo']) ?></div>
      <?php if (!empty($m['descricao'])): ?><div class="muted"><?= e($m['descricao']) ?></div><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
