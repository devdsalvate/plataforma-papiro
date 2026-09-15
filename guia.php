<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/learning_content.php';
$pdo=db();try{ensure_learning_content_current($pdo);}catch(Throwable $e){}
$slug=(string)($_GET['a']??'');
if($slug!==''){$st=$pdo->prepare('SELECT * FROM guia_artigos WHERE slug=?');$st->execute([$slug]);$art=$st->fetch();if($art){$title=(string)$art['titulo'];$active='guia';include __DIR__.'/includes/header.php';?>
<div class="guide-article-shell"><a class="text-link" href="<?= e(url('guia.php')) ?>">← Voltar ao guia</a><header class="guide-article-head"><span class="guide-number"><?= e((string)$art['icone']) ?></span><div><span class="eyebrow">GUIA PRÁTICO · <?= e((string)$art['tempo']) ?></span><h1><?= e((string)$art['titulo']) ?></h1></div></header><article class="card article guide-article-body"><?= (string)$art['texto'] ?></article><div class="guide-next"><a class="btn btn-primary" href="<?= e(url('trilhas.php')) ?>">Aplicar em uma trilha</a><a class="btn" href="<?= e(url('questoes.php')) ?>">Ir para questões</a></div></div>
<?php include __DIR__.'/includes/footer.php';exit;}}
$arts=$pdo->query('SELECT * FROM guia_artigos ORDER BY id')->fetchAll();$title='Guia de estudos';$active='guia';include __DIR__.'/includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">APRENDA A ESTUDAR</span><h1>Guia de estudos</h1><p class="muted">Para quem está começando a estudar sozinho: da primeira semana até simulados, revisão e organização por edital.</p></div></div>
<div class="guide-start card"><div><span class="eyebrow">COMECE AQUI</span><h2>Não sabe montar rotina?</h2><p>Leia primeiro “Nunca estudei sozinho”. Depois vá para rotina, teoria e questões. O restante pode ser usado conforme sua dificuldade.</p></div><a class="btn btn-primary" href="<?= e(url('guia.php?a=comecar')) ?>">Abrir primeiro guia</a></div>
<div class="guia-grid rich-guide-grid"><?php foreach($arts as $a): ?><a class="guia-card rich-guide-card" href="<?= e(url('guia.php?a='.$a['slug'])) ?>"><span class="guide-card-number"><?= e((string)$a['icone']) ?></span><div><h3><?= e((string)$a['titulo']) ?></h3><span class="muted"><?= e((string)$a['tempo']) ?> de leitura</span></div><span class="guide-arrow">→</span></a><?php endforeach; ?></div>
<?php include __DIR__.'/includes/footer.php'; ?>
