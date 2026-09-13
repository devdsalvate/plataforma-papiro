<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$fConcurso = (string)($_GET['concurso'] ?? '');
if (!in_array($fConcurso, CONCURSOS_TODOS, true)) $fConcurso = '';

if ($fConcurso !== '') {
    $st = db()->prepare('SELECT * FROM videoaulas WHERE concurso = ? ORDER BY id DESC');
    $st->execute([$fConcurso]);
    $videos = $st->fetchAll();
} else {
    $videos = db()->query('SELECT * FROM videoaulas ORDER BY id DESC')->fetchAll();
}

$title = 'Videoaulas';
$active = 'videos';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>🎥 Videoaulas</h1></div>
<div class="tabs">
  <a class="tab <?= $fConcurso === '' ? 'active' : '' ?>" href="<?= e(url('videoaulas.php')) ?>">Todas</a>
  <?php foreach (CONCURSOS_FOCO as $c): ?>
    <a class="tab <?= $fConcurso === $c ? 'active' : '' ?>" href="<?= e(url('videoaulas.php?concurso=' . urlencode($c))) ?>"><?= concurso_icone($c) ?> <?= e(concurso_nome($c)) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$videos): ?>
  <div class="card"><p class="muted">Nenhuma videoaula aqui ainda. Volte em breve! 🎬</p></div>
<?php endif; ?>
<div class="video-grid">
  <?php foreach ($videos as $v): ?>
  <div class="video-card">
    <div class="video-thumb">▶️</div>
    <div class="v-body">
      <b><?= e($v['titulo']) ?></b>
      <p class="muted"><?= e($v['descricao'] ?? '') ?></p>
      <div class="q-badges">
        <span class="badge b-navy"><?= concurso_icone($v['concurso']) ?> <?= e(concurso_nome($v['concurso'])) ?></span>
        <span class="badge"><?= e($v['materia']) ?></span>
      </div>
      <?php if (!empty($v['url'])): ?><p><a class="btn btn-small btn-gold" href="<?= e($v['url']) ?>" target="_blank" rel="noopener">▶️ Assistir</a></p><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
