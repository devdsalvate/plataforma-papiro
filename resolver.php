<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=current_user(); $uid=$user?(int)$user['id']:0; $pdo=db();
$id=(int)($_GET['id']??0);
$st=$pdo->prepare('SELECT * FROM questoes WHERE id=? AND ativo=1'); $st->execute([$id]); $q=$st->fetch();
if(!$q){ $title='Questão não encontrada';$active='questoes';include __DIR__.'/includes/header.php';echo '<div class="card"><p>Questão não encontrada ou inativa. <a href="'.e(url('questoes.php')).'">Voltar</a></p></div>';include __DIR__.'/includes/footer.php';exit; }
$alts=q_alternativas($q); $nonEmpty=array_values(array_filter($alts,fn($a)=>trim((string)$a)!==''));
$gab=(int)$q['gabarito']; $gradable=($gab>=0&&$gab<=4&&count($nonEmpty)>=2);
$ultima=null;$fav=false;
if($uid){ $m=user_answers_map($uid,[(int)$q['id']]);$ultima=$m[(int)$q['id']]??null;$st=$pdo->prepare('SELECT 1 FROM favoritos WHERE user_id=? AND questao_id=?');$st->execute([$uid,$q['id']]);$fav=(bool)$st->fetchColumn(); }
$st=$pdo->prepare('SELECT COUNT(*) t,COALESCE(SUM(correta),0) c FROM respostas WHERE questao_id=?');$st->execute([$q['id']]);$qs=$st->fetch();
$st=$pdo->prepare('SELECT c.*,u.nome FROM comentarios c JOIN users u ON u.id=c.user_id WHERE c.questao_id=? AND c.aprovado=1 ORDER BY c.id DESC LIMIT 30');$st->execute([$q['id']]);$comments=$st->fetchAll();

// Navegação de simulado, quando a questão foi aberta a partir do gerador.
$sim=(string)($_GET['sim']??'')==='1'; $simPos=max(0,(int)($_GET['pos']??0)); $nextId=null;$prevId=null;
if($sim && !empty($_SESSION['simulado_ids']) && is_array($_SESSION['simulado_ids'])){
  $ids=array_values(array_map('intval',$_SESSION['simulado_ids']));
  if(isset($ids[$simPos+1]))$nextId=$ids[$simPos+1]; if($simPos>0&&isset($ids[$simPos-1]))$prevId=$ids[$simPos-1];
}else{
  if($uid){$st=$pdo->prepare('SELECT id FROM questoes WHERE ativo=1 AND concurso=? AND id!=? AND NOT EXISTS (SELECT 1 FROM respostas r WHERE r.user_id=? AND r.questao_id=questoes.id) LIMIT 50');$st->execute([$q['concurso'],$q['id'],$uid]);}
  else{$st=$pdo->prepare('SELECT id FROM questoes WHERE ativo=1 AND concurso=? AND id!=? LIMIT 50');$st->execute([$q['concurso'],$q['id']]);}
  $cand=$st->fetchAll(PDO::FETCH_COLUMN); if($cand)$nextId=(int)$cand[array_rand($cand)];
}

$pv=null;
if(!empty($q['origem'])&&(int)($q['pagina']??0)>0&&preg_match('/^(\d*\.?\d+)-(\d*\.?\d+)$/',(string)($q['regiao']??''),$mm)){
  $base=basename((string)$q['origem']);
  if(is_file(APP_ROOT.'/simulados/'.$base))$pv=['url'=>url('simulados/'.rawurlencode($base)),'page'=>(int)$q['pagina'],'y0'=>(float)$mm[1],'y1'=>(float)$mm[2]];
}
$title=concurso_nome((string)$q['concurso']).' '.(int)$q['ano'].' · '.$q['materia'];$active='questoes';
$extra_js='<script type="module" src="'.e(url('assets/js/question-previews.js')).'"></script>';
include __DIR__.'/includes/header.php';
?>
<div class="page-head">
  <div><a class="btn btn-small" href="<?= e($sim?url('simulados.php'):url('questoes.php?concurso='.urlencode((string)$q['concurso']))) ?>">Voltar</a><h1 style="margin-top:10px"><?= e(concurso_nome((string)$q['concurso'])) ?> · <?= (int)$q['ano'] ?></h1></div>
  <span class="spacer"></span><?php if($sim): ?><span class="badge b-blue">Simulado · questão <?= $simPos+1 ?></span><?php endif; ?>
</div>

<div class="card question-shell" id="questaoBox" data-qid="<?= (int)$q['id'] ?>" data-answered="0">
  <div class="question-topline"><span class="badge b-gold"><?= e($q['materia']) ?></span><span class="badge"><?= e($q['assunto']) ?></span><?php if((int)$q['pagina']>0): ?><span class="badge">PDF p. <?= (int)$q['pagina'] ?></span><?php endif; ?><?php if($gradable&&(int)$qs['t']>0): ?><span class="badge b-blue"><?= (int)round($qs['c']*100/max(1,$qs['t'])) ?>% de acerto</span><?php endif; ?></div>

  <?php if($pv): ?>
  <figure class="question-origin-preview question-preview" data-question-preview data-pdf="<?= e($pv['url']) ?>" data-page="<?= (int)$pv['page'] ?>" data-y0="<?= e((string)$pv['y0']) ?>" data-y1="<?= e((string)$pv['y1']) ?>">
    <div class="preview-head"><span>Prévia oficial da questão</span><a href="<?= e($pv['url'].'#page='.$pv['page']) ?>" target="_blank" rel="noopener">Abrir PDF</a></div>
    <div class="question-preview-placeholder">Carregando a página original...</div><canvas hidden></canvas>
  </figure>
  <?php endif; ?>

  <div class="enunciado"><?= $q['enunciado'] ?></div>
  <div id="feedback"></div>

  <?php if(count($nonEmpty)>=2): ?>
  <div class="alts"><?php foreach($alts as $i=>$a): if(trim((string)$a)==='')continue; ?><div class="alt" data-alt="<?= $i ?>"><span class="alt-letter"><?= LETRAS[$i] ?></span><span><?= e($a) ?></span></div><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if($uid && $gradable): ?>
    <button class="btn btn-primary" id="btnResponder" disabled>Responder</button>
    <button class="btn" id="btnVerResolucao" type="button" onclick="document.getElementById('resolucaoBox').style.display='';this.style.display='none'">Ver resolução</button>
  <?php elseif(!$uid && $gradable): ?>
    <p><a class="btn btn-primary" href="<?= e(url('login.php?next='.urlencode($_SERVER['REQUEST_URI']))) ?>">Entre para responder</a></p>
  <?php else: ?>
    <div class="flash info" style="display:block">Esta questão está preservada como questão oficial visual. O gabarito ainda não foi validado no banco; por isso a plataforma não marca uma alternativa como correta automaticamente.</div>
    <?php if($uid): ?><button class="btn btn-primary" id="btnIaQuestao" type="button">Explicar com Papiro IA</button><div class="resolucao" id="iaQuestaoBox" style="display:none;margin-top:10px"></div><?php endif; ?>
  <?php endif; ?>

  <?php if($gradable): ?>
  <div class="resolucao" id="resolucaoBox" style="display:none"><b>Resolução comentada</b><div class="res-body"><p><?= $q['resolucao'] ?></p></div><?php if($uid): ?><div class="q-actions"><button class="btn btn-small" id="btnIaQuestao">Explicar com Papiro IA</button></div><div class="resolucao" id="iaQuestaoBox" style="display:none;margin-top:10px"></div><?php endif; ?></div>
  <?php endif; ?>

  <?php if($uid): ?><div class="q-actions"><button class="btn btn-small fav-btn <?= $fav?'faved':'' ?>" data-fav="<?= (int)$q['id'] ?>"><?= $fav?'Favoritada':'Favoritar' ?></button><a class="btn btn-small" href="<?= e(url('caderno.php')) ?>">Meu caderno</a><?php if($sim&&$prevId): ?><a class="btn btn-small" href="<?= e(url('resolver.php?id='.$prevId.'&sim=1&pos='.($simPos-1))) ?>">Anterior</a><?php endif; ?><?php if($nextId): ?><a class="btn btn-small btn-primary" id="nextBox" href="<?= e($sim?url('resolver.php?id='.$nextId.'&sim=1&pos='.($simPos+1)):url('resolver.php?id='.$nextId)) ?>">Próxima questão</a><?php elseif($sim): ?><a class="btn btn-small btn-primary" href="<?= e(url('simulados.php')) ?>">Finalizar simulado</a><?php endif; ?></div><?php endif; ?>
</div>

<div class="card comments">
  <h3>Comentários (<?= count($comments) ?>)</h3>
  <?php if($uid): ?><form id="commentForm" data-qid="<?= (int)$q['id'] ?>" class="form"><label>Publique sua resolução ou dúvida<textarea id="commentText" style="min-height:70px" placeholder="Escreva sua resolução ou dúvida..."></textarea></label><button class="btn btn-small btn-primary" type="submit">Publicar</button></form><?php else: ?><p class="muted"><a href="<?= e(url('login.php')) ?>">Entre</a> para comentar.</p><?php endif; ?>
  <div id="commentList" style="margin-top:12px"><?php if(!$comments): ?><p class="muted" id="noComments">Ainda não há comentários.</p><?php endif; ?><?php foreach($comments as $c): ?><div class="comment"><span class="who"><?= e($c['nome']) ?></span><span class="when"><?= e(fmt_data($c['created_at'])) ?></span><div><?= nl2br(e($c['texto'])) ?></div></div><?php endforeach; ?></div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
