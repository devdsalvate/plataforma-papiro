<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/learning_content.php';
$user=current_user();$uid=$user?(int)$user['id']:0;$pdo=db();
try { ensure_learning_content_current($pdo); } catch(Throwable $e) {}
$slug=(string)($_GET['slug']??'');$st=$pdo->prepare('SELECT * FROM trilhas WHERE slug=? AND ativo=1');$st->execute([$slug]);$t=$st->fetch();
if(!$t){$title='Trilha não encontrada';$active='trilhas';include __DIR__.'/includes/header.php';echo '<div class="card"><p>Trilha não encontrada. <a href="'.e(url('trilhas.php')).'">Voltar</a></p></div>';include __DIR__.'/includes/footer.php';exit;}
$st=$pdo->prepare('SELECT * FROM trilha_modulos WHERE trilha_id=? ORDER BY ordem,id');$st->execute([$t['id']]);$modulos=$st->fetchAll();
$feitos=[];if($uid){$st=$pdo->prepare('SELECT modulo_id FROM trilha_progresso p JOIN trilha_modulos m ON m.id=p.modulo_id WHERE p.user_id=? AND m.trilha_id=?');$st->execute([$uid,$t['id']]);$feitos=array_flip($st->fetchAll(PDO::FETCH_COLUMN));}
$total=count($modulos);$pct=$total>0?(int)round(count($feitos)*100/$total):0;
$title=(string)$t['nome'];$active='trilhas';include __DIR__.'/includes/header.php';
?>
<div class="page-head trail-detail-head"><div><a class="text-link" href="<?= e(url('trilhas.php')) ?>">← Todas as trilhas</a><span class="eyebrow">TRILHA DETALHADA</span><h1><?= e((string)$t['nome']) ?></h1><p class="muted"><?= e((string)($t['descricao']??'')) ?></p></div><div class="trail-big-progress"><strong id="trilhaPct"><?= $pct ?>%</strong><span><?= count($feitos) ?>/<?= $total ?> módulos</span></div></div>
<div class="card trail-progress-card"><div class="progress"><div class="progress-bar" id="trilhaBar" style="width:<?= $pct ?>%"></div></div><?php if(!$uid): ?><p class="muted"><a href="<?= e(url('login.php')) ?>">Entre</a> para salvar progresso.</p><?php endif; ?></div>
<div class="trail-howto"><b>Como usar:</b> estude os conteúdos, cumpra a prática e só marque o módulo quando atingir o critério de domínio. Marcar sem dominar esconde a lacuna; repetir um módulo faz parte do processo.</div>
<div class="module-timeline">
<?php $lastPhase=''; foreach($modulos as $i=>$row):
  $data=json_decode((string)($row['descricao']??''),true); if(!is_array($data))$data=['objetivo'=>(string)($row['descricao']??''),'conteudos'=>[],'pratica'=>[],'meta'=>'','dominio'=>'','tempo'=>'','materia'=>'Geral','fase'=>''];
  $done=isset($feitos[$row['id']]);$phase=(string)($data['fase']??'');
  if($phase!==''&&$phase!==$lastPhase):$lastPhase=$phase;?><div class="phase-divider"><span><?= e($phase) ?></span></div><?php endif; ?>
  <article class="module-card <?= $done?'done':'' ?>">
    <div class="module-check"><input type="checkbox" data-modulo="<?= (int)$row['id'] ?>" <?= $done?'checked':'' ?> <?= $uid?'':'disabled' ?> aria-label="Concluir módulo"></div>
    <div class="module-body">
      <div class="module-heading"><div><span class="module-index">M<?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span><h2><?= e((string)$row['titulo']) ?></h2></div><div class="module-tags"><span class="badge b-blue"><?= e((string)($data['materia']??'Geral')) ?></span><?php if(!empty($data['tempo'])):?><span class="badge"><?= e((string)$data['tempo']) ?></span><?php endif; ?></div></div>
      <?php if(!empty($data['objetivo'])):?><p class="module-objective"><?= e((string)$data['objetivo']) ?></p><?php endif; ?>
      <div class="module-columns">
        <div><h3>Conteúdo</h3><ul><?php foreach((array)($data['conteudos']??[]) as $x): ?><li><?= e((string)$x) ?></li><?php endforeach; ?></ul></div>
        <div><h3>Prática</h3><ul><?php foreach((array)($data['pratica']??[]) as $x): ?><li><?= e((string)$x) ?></li><?php endforeach; ?></ul></div>
      </div>
      <div class="module-targets"><div><span>Meta</span><b><?= e((string)($data['meta']??'—')) ?></b></div><div><span>Avance quando</span><b><?= e((string)($data['dominio']??'—')) ?></b></div></div>
      <?php if(!empty($data['materia']) && in_array((string)$data['materia'],MATERIAS,true)): ?><a class="btn btn-small" href="<?= e(url('questoes.php?materia='.urlencode((string)$data['materia']))) ?>">Praticar esta matéria</a><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
