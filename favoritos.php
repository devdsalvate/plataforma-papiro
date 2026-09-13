<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];

$st = db()->prepare('SELECT q.* FROM favoritos f JOIN questoes q ON q.id = f.questao_id WHERE f.user_id = ? AND q.ativo = 1 ORDER BY f.created_at DESC');
$st->execute([$uid]);
$lista = $st->fetchAll();

$title = 'Favoritos';
$active = 'favoritos';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>⭐ Favoritos</h1><span class="badge b-gold"><?= count($lista) ?></span></div>

<?php if (!$lista): ?>
  <div class="card"><p>Você ainda não favoritou nada. Toque em <b>☆ Favoritar</b> em qualquer questão para guardá-la aqui!</p></div>
<?php endif; ?>

<?php foreach ($lista as $q):
    $snippet = mb_substr(trim(strip_tags($q['enunciado'])), 0, 150) . '…'; ?>
<div class="q-item" data-fav-row>
  <div class="q-badges">
    <span class="badge b-navy"><?= concurso_icone($q['concurso']) ?> <?= e(concurso_nome($q['concurso'])) ?></span>
    <span class="badge"><?= (int)$q['ano'] ?></span>
    <span class="badge b-gold"><?= e($q['materia']) ?></span>
    <span class="q-status"><button class="btn btn-small fav-btn faved" data-fav="<?= (int)$q['id'] ?>" data-remove-row>⭐ Favoritada</button></span>
  </div>
  <a href="<?= e(url('resolver.php?id=' . $q['id'])) ?>" style="color:inherit"><div class="q-snippet"><?= e($snippet) ?></div></a>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
