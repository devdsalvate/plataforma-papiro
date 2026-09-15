<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/learning_content.php';
$user=current_user(); $uid=$user?(int)$user['id']:0; $pdo=db();
try { ensure_learning_content_current($pdo); } catch(Throwable $e) {}
$trilhas=$pdo->query("SELECT * FROM trilhas WHERE ativo=1 ORDER BY CASE WHEN slug='base' THEN 0 ELSE 1 END,nome")->fetchAll();
$title='Trilhas de estudos';$active='trilhas';include __DIR__.'/includes/header.php';
?>
<div class="page-head trail-page-head"><div><span class="eyebrow">PLANO DE CONTEÚDO</span><h1>Trilhas de estudos</h1><p class="muted">Cada trilha agora combina conteúdo, prática, metas de questões e critério de domínio. Use o edital vigente como fonte final para matérias e regras do seu ano.</p></div></div>
<div class="trail-callout trail-callout-actions"><div><b>Se sua Matemática tem muitas lacunas:</b> comece por <a href="<?= e(url('trilha.php?slug=base')) ?>">Base Forte em Matemática</a> antes de tentar acelerar para listas avançadas.</div><a class="btn btn-small" href="<?= e(url('simulados.php?gerar=1&modo=personalizado&qtd=20&materia=Matemática')) ?>">Fazer diagnóstico de 20 questões</a></div>
<div class="trilha-grid rich-trail-grid">
<?php foreach($trilhas as $t):
  [$total,$feitos,$pct]=$uid?trilha_progresso($uid,(int)$t['id']):[0,0,0];
  if(!$uid){$st=$pdo->prepare('SELECT COUNT(*) FROM trilha_modulos WHERE trilha_id=?');$st->execute([$t['id']]);$total=(int)$st->fetchColumn();}
?>
<a class="trilha-card rich-trail-card" style="--trail-color:<?= e((string)$t['cor']) ?>" href="<?= e(url('trilha.php?slug='.$t['slug'])) ?>">
  <div class="trail-card-top"><span class="trail-code"><?= e((string)$t['icone']) ?></span><span class="badge"><?= $total ?> módulos</span></div>
  <h3><?= e((string)$t['nome']) ?></h3>
  <p><?= e((string)($t['descricao']??'')) ?></p>
  <?php if($uid): ?><div class="trail-progress-copy"><span><?= $feitos ?> concluídos</span><b><?= $pct ?>%</b></div><div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div><?php else: ?><span class="text-link">Ver plano completo →</span><?php endif; ?>
</a>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
