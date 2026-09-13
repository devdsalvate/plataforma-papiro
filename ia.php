<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$pdo = db();

$qctx = null;
$qidCtx = (int)($_GET['q'] ?? 0);
if ($qidCtx > 0) {
    $st = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND ativo = 1');
    $st->execute([$qidCtx]);
    $qctx = $st->fetch() ?: null;
}

$st = $pdo->prepare('SELECT * FROM ia_perguntas WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$st->execute([$uid]);
$hist = array_reverse($st->fetchAll());

$title = 'Papiro IA';
$active = 'ia';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>🤖 Papiro IA</h1></div>
<?php if (GROQ_API_KEY === ''): ?>
  <div class="flash info">Modo demonstração: a chave da Groq não está configurada — as respostas usarão as resoluções cadastradas. Veja o README para ativar a IA real.</div>
<?php endif; ?>
<?php if ($qctx): ?>
  <div class="card">📌 Contexto: <b><?= concurso_icone($qctx['concurso']) ?> <?= e(concurso_nome($qctx['concurso'])) ?> <?= (int)$qctx['ano'] ?> · <?= e($qctx['materia']) ?></b>
  <a href="<?= e(url('resolver.php?id=' . $qctx['id'])) ?>">(ver questão)</a></div>
<?php endif; ?>

<div class="card">
  <div class="chat" id="iaLog">
    <?php if (!$hist): ?><div class="msg ia">👋 Olá, futuro(a) oficial! Pergunte sobre qualquer assunto de <b>EFOMM, EPCAR, EEAR, Colégio Naval ou ITA</b> — ou peça para explicar uma questão passo a passo.</div><?php endif; ?>
    <?php foreach ($hist as $h): ?>
      <div class="msg user"><?= e($h['pergunta']) ?></div>
      <div class="msg ia"><?= $h['resposta'] ?></div>
    <?php endforeach; ?>
  </div>
  <form class="chat-form" id="iaForm" data-qid="<?= $qctx ? (int)$qctx['id'] : '' ?>">
    <input id="iaInput" placeholder="Ex.: Explique determinantes de forma simples..." autocomplete="off">
    <button class="btn btn-gold" type="submit">Enviar</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
