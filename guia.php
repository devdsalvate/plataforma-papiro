<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pdo = db();

$slug = (string)($_GET['a'] ?? '');
if ($slug !== '') {
    $st = $pdo->prepare('SELECT * FROM guia_artigos WHERE slug = ?');
    $st->execute([$slug]);
    $art = $st->fetch();
    if ($art) {
        $title = $art['titulo'];
        $active = 'guia';
        include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <a class="btn btn-small" href="<?= e(url('guia.php')) ?>">← Guia</a>
  <h1><?= e($art['icone'] . ' ' . $art['titulo']) ?></h1>
  <span class="badge">⏱️ <?= e($art['tempo']) ?></span>
</div>
<div class="card article"><p><?= $art['texto'] ?></p></div>
<?php
        include __DIR__ . '/includes/footer.php';
        exit;
    }
}

$arts = $pdo->query('SELECT * FROM guia_artigos ORDER BY id')->fetchAll();
$title = 'Guia de estudos';
$active = 'guia';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>📖 Guia de estudos</h1></div>
<p class="muted">Aprenda <b>como estudar</b>: método, revisão, simulados e organização.</p>
<div class="guia-grid">
  <?php foreach ($arts as $a): ?>
    <a class="guia-card" href="<?= e(url('guia.php?a=' . $a['slug'])) ?>">
      <div style="font-size:1.8rem"><?= e($a['icone']) ?></div>
      <h3><?= e($a['titulo']) ?></h3>
      <span class="badge">⏱️ <?= e($a['tempo']) ?> de leitura</span>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
