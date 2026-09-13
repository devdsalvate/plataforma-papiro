<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$uid = $user ? (int)$user['id'] : 0;

$trilhas = db()->query('SELECT * FROM trilhas WHERE ativo = 1 ORDER BY id')->fetchAll();
$title = 'Trilhas de estudos';
$active = 'trilhas';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>🗺️ Trilhas de estudos</h1></div>
<p class="muted">Saiba exatamente <b>o que estudar</b>, na ordem certa, para cada concurso.</p>

<div class="trilha-grid">
<?php foreach ($trilhas as $t):
    [$total, $feitos, $pct] = $uid ? trilha_progresso($uid, (int)$t['id']) : [0, 0, 0];
    if (!$uid) {
        $st = db()->prepare('SELECT COUNT(*) FROM trilha_modulos WHERE trilha_id = ?');
        $st->execute([$t['id']]);
        $total = (int)$st->fetchColumn();
    }
?>
  <a class="trilha-card" style="border-top-color:<?= e($t['cor']) ?>" href="<?= e(url('trilha.php?slug=' . $t['slug'])) ?>">
    <div style="font-size:2rem"><?= e($t['icone']) ?></div>
    <h3><?= e($t['nome']) ?></h3>
    <p class="muted"><?= $total ?> módulos<?= $uid ? " · $feitos concluídos" : '' ?></p>
    <?php if ($uid): ?>
      <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
      <p><b><?= $pct ?>%</b></p>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
