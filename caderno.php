<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=require_login();$uid=(int)$user['id'];$pdo=db();
$fConcurso=(string)($_GET['concurso']??'');if(!in_array($fConcurso,CONCURSOS_TODOS,true))$fConcurso='';
$fRev=(string)($_GET['rev']??'todas');if(!in_array($fRev,['todas','hoje','futuras','concluidas'],true))$fRev='todas';
$fTipo=(string)($_GET['tipo']??'');$tipos=['conteudo','calculo','interpretacao','distracao','tempo','chute'];if(!in_array($fTipo,$tipos,true))$fTipo='';
$where=['c.user_id=?'];$params=[$uid];if($fConcurso!==''){$where[]='q.concurso=?';$params[]=$fConcurso;}if($fTipo!==''){$where[]='c.erro_tipo=?';$params[]=$fTipo;}
if($fRev==='hoje'){$where[]='c.revisada=0 AND (c.proxima_revisao IS NULL OR c.proxima_revisao<=?)';$params[]=today_str();}
elseif($fRev==='futuras'){$where[]='c.revisada=0 AND c.proxima_revisao>?';$params[]=today_str();}
elseif($fRev==='concluidas')$where[]='c.revisada=1';
$sql='SELECT c.*,q.concurso,q.ano,q.materia,q.assunto,q.dificuldade,q.enunciado, '
    .'(SELECT r.confianca FROM respostas r WHERE r.user_id=c.user_id AND r.questao_id=c.questao_id ORDER BY r.id DESC LIMIT 1) ultima_confianca '
    .'FROM caderno_erros c JOIN questoes q ON q.id=c.questao_id WHERE '.implode(' AND ',$where).' ORDER BY c.revisada ASC, COALESCE(c.proxima_revisao,\'1900-01-01\') ASC,c.created_at DESC';
$st=$pdo->prepare($sql);$st->execute($params);$erros=$st->fetchAll();
$due=review_due_count($uid);$st=$pdo->prepare('SELECT erro_tipo,COUNT(*) n FROM caderno_erros WHERE user_id=? AND erro_tipo<>\'\' GROUP BY erro_tipo ORDER BY n DESC');$st->execute([$uid]);$typeStats=$st->fetchAll();
$title='Caderno de erros';$active='caderno';include __DIR__.'/includes/header.php';
$labels=['conteudo'=>'Conteúdo','calculo'=>'Cálculo','interpretacao'=>'Interpretação','distracao'=>'Distração','tempo'=>'Tempo','chute'=>'Chute'];
?>
<div class="page-head"><div><span class="eyebrow">APRENDER COM O ERRO</span><h1>Caderno de erros</h1><p class="muted">Classifique a causa, anote a regra correta e use a revisão espaçada para impedir que o erro volte.</p></div><span class="badge <?= $due?'b-red':'b-green' ?>"><?= $due ?> revisão(ões) vencendo</span></div>
<?php if($typeStats):?><div class="error-type-stats"><?php foreach($typeStats as $s):?><div><strong><?= (int)$s['n'] ?></strong><span><?= e($labels[$s['erro_tipo']]??$s['erro_tipo']) ?></span></div><?php endforeach;?></div><?php endif;?>
<form class="filters" method="get"><label>Concurso<select name="concurso"><option value="">Todos</option><?php foreach(CONCURSOS_TODOS as $c):?><option value="<?= e($c) ?>" <?= $fConcurso===$c?'selected':'' ?>><?= e(concurso_nome($c)) ?></option><?php endforeach;?></select></label><label>Agenda<select name="rev"><option value="todas" <?= $fRev==='todas'?'selected':'' ?>>Todos</option><option value="hoje" <?= $fRev==='hoje'?'selected':'' ?>>Revisar hoje</option><option value="futuras" <?= $fRev==='futuras'?'selected':'' ?>>Agendadas</option><option value="concluidas" <?= $fRev==='concluidas'?'selected':'' ?>>Ciclo concluído</option></select></label><label>Causa<select name="tipo"><option value="">Todas</option><?php foreach($labels as $k=>$v):?><option value="<?= e($k) ?>" <?= $fTipo===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach;?></select></label><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn" href="<?= e(url('caderno.php')) ?>">Limpar</a></form>
<?php if(!$erros):?><div class="card empty-state"><b>Nada nesta fila.</b><p>Continue praticando ou altere os filtros.</p></div><?php endif;?>
<div class="error-notebook-list">
<?php foreach($erros as $r):$snippet=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)$r['enunciado']))??''),0,220);$isDue=!(int)$r['revisada']&&(!$r['proxima_revisao']||$r['proxima_revisao']<=today_str());?>
<article class="card error-note <?= $isDue?'due':'' ?>" data-err-row>
  <div class="error-note-head"><div class="q-badges"><span class="badge b-navy"><?= e(concurso_nome((string)$r['concurso'])) ?> <?= (int)$r['ano'] ?></span><span class="badge b-blue"><?= e($r['materia']) ?></span><?php if($r['assunto']):?><span class="badge"><?= e($r['assunto']) ?></span><?php endif;?></div><div class="review-date"><small>Próxima revisão</small><b><?= (int)$r['revisada']?'Ciclo concluído':($r['proxima_revisao']?date('d/m/Y',strtotime((string)$r['proxima_revisao'])):'Hoje') ?></b></div></div>
  <a class="error-question-link" href="<?= e(url('resolver.php?id='.(int)$r['questao_id'])) ?>"><?= e($snippet) ?><?= mb_strlen($snippet)>=220?'…':'' ?></a>
  <div class="error-note-grid"><label>Causa do erro<select data-error-select="<?= (int)$r['questao_id'] ?>"><option value="">Classificar...</option><?php foreach($labels as $k=>$v):?><option value="<?= e($k) ?>" <?= $r['erro_tipo']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach;?></select></label><div><small>Revisões concluídas</small><strong><?= (int)$r['revisoes'] ?>/5</strong><?php if($r['ultima_confianca']):?><small>Confiança na tentativa: <?= e((string)$r['ultima_confianca']) ?></small><?php endif;?></div></div>
  <label class="note-label">Regra/insight para não errar de novo<textarea class="note" data-note="<?= (int)$r['questao_id'] ?>" placeholder="Ex.: antes de usar Bhaskara, colocar a equação na forma ax²+bx+c=0."><?= e($r['anotacao']??'') ?></textarea></label>
  <div class="q-actions"><button class="btn btn-small" data-save-note="<?= (int)$r['questao_id'] ?>">Salvar anotação</button><?php if(!(int)$r['revisada']):?><a class="btn btn-small" href="<?= e(url('resolver.php?id='.(int)$r['questao_id'])) ?>">Refazer questão</a><button class="btn btn-small btn-primary" data-review-now="<?= (int)$r['questao_id'] ?>">Marcar revisão feita</button><?php endif;?><button class="btn btn-small btn-danger" data-remove-err="<?= (int)$r['questao_id'] ?>">Remover</button></div>
</article>
<?php endforeach;?></div>
<?php include __DIR__.'/includes/footer.php'; ?>
