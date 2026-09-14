<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=current_user(); $pdo=db();
$qtd=(int)($_GET['qtd'] ?? 10); if(!in_array($qtd,[5,10,15,20,30,40],true)) $qtd=10;
$concurso=(string)($_GET['concurso'] ?? ''); if(!in_array($concurso,CONCURSOS_TODOS,true)) $concurso='';
$materia=(string)($_GET['materia'] ?? ''); if(!in_array($materia,MATERIAS,true)) $materia='';
$ano=(string)($_GET['ano'] ?? ''); if($ano!=='' && !ctype_digit($ano)) $ano='';
$gerar=isset($_GET['gerar']);
$lista=[];
if($gerar){
  $where=['ativo=1']; $params=[];
  if($concurso!==''){ $where[]='concurso=?'; $params[]=$concurso; }
  if($materia!==''){ $where[]='materia=?'; $params[]=$materia; }
  if($ano!==''){ $where[]='ano=?'; $params[]=(int)$ano; }
  $driver=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  $rand=$driver==='sqlite'?'RANDOM()':'RAND()';
  $st=$pdo->prepare('SELECT * FROM questoes WHERE '.implode(' AND ',$where)." ORDER BY $rand LIMIT $qtd");
  $st->execute($params); $lista=$st->fetchAll();
  $_SESSION['simulado_ids']=array_map('intval',array_column($lista,'id'));
  $_SESSION['simulado_created']=time();
}
$anos=$pdo->query('SELECT DISTINCT ano FROM questoes WHERE ativo=1 AND ano>0 ORDER BY ano DESC')->fetchAll(PDO::FETCH_COLUMN);
function sim_preview(array $q): ?array {
  if(empty($q['origem']) || (int)($q['pagina']??0)<=0) return null;
  $base=basename((string)$q['origem']);
  if(!is_file(APP_ROOT.'/simulados/'.$base)) return null;
  if(!preg_match('/^(\d*\.?\d+)-(\d*\.?\d+)$/',(string)($q['regiao']??''),$m)) return null;
  return ['url'=>url('simulados/'.rawurlencode($base)),'page'=>(int)$q['pagina'],'y0'=>(float)$m[1],'y1'=>(float)$m[2]];
}
$title='Simulados'; $active='simulados';
$extra_js='<script type="module" src="'.e(url('assets/js/question-previews.js')).'"></script>';
include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><h1>Gerar simulado</h1><div class="muted">Monte uma bateria rápida usando o banco oficial e abra as questões na ordem escolhida.</div></div></div>
<div class="sim-builder">
  <div class="card">
    <h2>Configuração</h2>
    <form method="get" class="form">
      <input type="hidden" name="gerar" value="1">
      <div class="sim-options">
        <label>Quantidade<select name="qtd"><?php foreach([5,10,15,20,30,40] as $n): ?><option value="<?= $n ?>" <?= $qtd===$n?'selected':'' ?>><?= $n ?> questões</option><?php endforeach; ?></select></label>
        <label>Concurso<select name="concurso"><option value="">Todos</option><?php foreach(CONCURSOS_TODOS as $c): if($c==='Outro')continue; ?><option value="<?= e($c) ?>" <?= $concurso===$c?'selected':'' ?>><?= e(concurso_nome($c)) ?></option><?php endforeach; ?></select></label>
        <label>Disciplina<select name="materia"><option value="">Todas</option><?php foreach(MATERIAS as $m): if($m==='Geral')continue; ?><option value="<?= e($m) ?>" <?= $materia===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?></select></label>
        <label>Ano<select name="ano"><option value="">Todos</option><?php foreach($anos as $a): ?><option value="<?= (int)$a ?>" <?= $ano===(string)$a?'selected':'' ?>><?= (int)$a ?></option><?php endforeach; ?></select></label>
      </div>
      <button class="btn btn-primary" type="submit">Gerar simulado</button>
    </form>
  </div>
  <aside class="sim-summary"><div class="sim-kpi"><?= $gerar?count($lista):$qtd ?></div><h2>questões</h2><p>As questões são sorteadas do banco conforme os filtros. A prévia visual preserva gráficos, fórmulas e figuras do PDF original.</p></aside>
</div>

<?php if($gerar): ?>
<div class="page-head" style="margin-top:22px"><div><h2>Seu simulado</h2><div class="muted"><?= count($lista) ?> questão(ões) selecionada(s).</div></div><?php if($lista): ?><span class="spacer"></span><a class="btn btn-primary" href="<?= e(url('resolver.php?id='.(int)$lista[0]['id'].'&sim=1&pos=0')) ?>">Começar pela primeira</a><?php endif; ?></div>
<?php if(!$lista): ?><div class="card"><b>Nenhuma questão encontrada com esses filtros.</b></div><?php endif; ?>
<section class="question-results">
<?php foreach($lista as $i=>$q): $pv=sim_preview($q); $sn=trim(preg_replace('/\s+/u',' ',strip_tags((string)$q['enunciado']))??''); if(mb_strlen($sn)>330)$sn=mb_substr($sn,0,330).'…'; ?>
<article class="question-card">
  <div class="question-card-main"><div class="question-meta"><span class="question-id"><?= $i+1 ?>/<?= count($lista) ?></span><span class="badge b-navy"><?= e(concurso_nome((string)$q['concurso'])) ?></span><span class="badge"><?= (int)$q['ano'] ?></span><span class="badge b-blue"><?= e($q['materia']) ?></span></div><p class="question-title"><?= e($sn) ?></p><div class="question-topic"><?= e($q['assunto']) ?> · PDF p. <?= (int)$q['pagina'] ?></div><div class="question-actions"><a class="btn btn-primary" href="<?= e(url('resolver.php?id='.(int)$q['id'].'&sim=1&pos='.$i)) ?>">Abrir questão</a></div></div>
  <?php if($pv): ?><figure class="question-preview" data-question-preview data-pdf="<?= e($pv['url']) ?>" data-page="<?= (int)$pv['page'] ?>" data-y0="<?= e((string)$pv['y0']) ?>" data-y1="<?= e((string)$pv['y1']) ?>"><div class="question-preview-placeholder">Carregando prévia...</div><canvas hidden></canvas><figcaption>PDF original · página <?= (int)$pv['page'] ?></figcaption></figure><?php endif; ?>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
