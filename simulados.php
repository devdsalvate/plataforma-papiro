<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=current_user(); $uid=$user?(int)$user['id']:0; $pdo=db();

function sim_rand_sql(PDO $pdo): string { return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'RANDOM()':'RAND()'; }
function sim_pick(PDO $pdo, array $where, array $params, int $limit, bool $unanswered=false, int $uid=0): array {
    if($unanswered&&$uid){$where[]='NOT EXISTS (SELECT 1 FROM respostas r WHERE r.user_id=? AND r.questao_id=q.id)';$params[]=$uid;}
    $sql='SELECT q.* FROM questoes q WHERE '.implode(' AND ',$where).' ORDER BY '.sim_rand_sql($pdo).' LIMIT '.max(1,$limit);
    $st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll();
}
function sim_balanced(PDO $pdo,string $concurso,int $qtd,int $uid=0): array {
    $st=$pdo->prepare("SELECT materia,COUNT(*) n FROM questoes WHERE ativo=1 AND concurso=? AND materia<>'Geral' GROUP BY materia HAVING COUNT(*)>0 ORDER BY n DESC");$st->execute([$concurso]);$mats=$st->fetchAll(PDO::FETCH_COLUMN);
    if(!$mats)return sim_pick($pdo,['q.ativo=1','q.concurso=?'],[$concurso],$qtd,true,$uid);
    $bucket=[];$per=max(1,(int)ceil($qtd/count($mats)));
    foreach($mats as $m){foreach(sim_pick($pdo,['q.ativo=1','q.concurso=?','q.materia=?'],[$concurso,$m],$per,true,$uid) as $q)$bucket[(int)$q['id']]=$q;}
    if(count($bucket)<$qtd){foreach(sim_pick($pdo,['q.ativo=1','q.concurso=?'],[$concurso],$qtd*2,false,$uid) as $q){$bucket[(int)$q['id']]=$q;if(count($bucket)>=$qtd)break;}}
    $out=array_values($bucket);shuffle($out);return array_slice($out,0,$qtd);
}

// Resultado persistente.
$resultId=(int)($_GET['resultado']??0);
if($resultId>0){
    $user=require_login();$uid=(int)$user['id'];
    $st=$pdo->prepare('SELECT * FROM simulados_execucoes WHERE id=? AND user_id=?');$st->execute([$resultId,$uid]);$sim=$st->fetch();
    if(!$sim){flash('error','Simulado não encontrado.');redirect('simulados.php');}
    if(empty($sim['fim'])){$pdo->prepare('UPDATE simulados_execucoes SET fim=? WHERE id=?')->execute([now_str(),$resultId]);$sim['fim']=now_str();}
    $st=$pdo->prepare('SELECT sr.*,q.materia,q.assunto,q.concurso,q.ano,q.enunciado FROM simulado_respostas sr JOIN questoes q ON q.id=sr.questao_id WHERE sr.execucao_id=? ORDER BY sr.created_at');$st->execute([$resultId]);$answers=$st->fetchAll();
    $by=[];foreach($answers as $a){$m=(string)$a['materia'];if(!isset($by[$m]))$by[$m]=['n'=>0,'a'=>0,'ok'=>0];$by[$m]['n']++;if((int)$a['avaliavel']){$by[$m]['a']++;$by[$m]['ok']+=(int)$a['correta'];}}
    $taxa=(int)$sim['avaliadas']>0?(int)round((int)$sim['acertos']*100/(int)$sim['avaliadas']):0;
    $title='Resultado do simulado';$active='simulados';include __DIR__.'/includes/header.php';?>
    <div class="page-head"><div><span class="eyebrow">RESULTADO</span><h1>Simulado #<?= (int)$sim['id'] ?></h1><p class="muted"><?= e(ucfirst((string)$sim['modo'])) ?> · <?= e((string)$sim['concurso']?:'misto') ?> · <?= e(fmt_data((string)$sim['inicio'])) ?></p></div><a class="btn btn-primary" href="<?= e(url('simulados.php')) ?>">Novo simulado</a></div>
    <div class="dashboard-kpis"><div class="kpi-card"><span>Respondidas</span><strong><?= (int)$sim['respondidas'] ?>/<?= (int)$sim['qtd'] ?></strong></div><div class="kpi-card"><span>Avaliadas</span><strong><?= (int)$sim['avaliadas'] ?></strong></div><div class="kpi-card"><span>Acertos</span><strong><?= (int)$sim['acertos'] ?></strong></div><div class="kpi-card accent"><span>Precisão</span><strong><?= $taxa ?>%</strong></div></div>
    <div class="dashboard-grid dashboard-grid-secondary"><section class="card"><div class="section-head"><div><span class="eyebrow">POR MATÉRIA</span><h2>Onde você ganhou e perdeu pontos</h2></div></div><?php foreach($by as $m=>$r):$p=$r['a']?(int)round($r['ok']*100/$r['a']):0;?><div class="performance-row"><div class="performance-label"><b><?= e($m) ?></b><small><?= $r['ok'] ?>/<?= $r['a'] ?> avaliadas</small></div><div class="performance-meter"><span style="width:<?= $p ?>%"></span></div><strong><?= $r['a']?$p.'%':'—' ?></strong></div><?php endforeach; ?></section>
    <section class="card"><div class="section-head"><div><span class="eyebrow">PRÓXIMA AÇÃO</span><h2>Transforme o resultado em estudo</h2></div></div><p>Refaça as erradas no caderno, classifique a causa do erro e programe a revisão. Depois gere um simulado inteligente para os assuntos mais fracos.</p><div class="q-actions"><a class="btn btn-primary" href="<?= e(url('caderno.php?rev=hoje')) ?>">Abrir caderno de erros</a><a class="btn" href="<?= e(url('simulados.php?modo=inteligente')) ?>">Novo inteligente</a></div></section></div>
    <section class="card"><div class="section-head"><div><span class="eyebrow">QUESTÕES</span><h2>Revisão do simulado</h2></div></div><?php if(!$answers):?><p class="muted">Nenhuma resposta registrada.</p><?php endif;?><?php foreach($answers as $i=>$a):$sn=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)$a['enunciado']))??''),0,180);?><a class="result-question-row" href="<?= e(url('resolver.php?id='.(int)$a['questao_id'])) ?>"><span><?= $i+1 ?></span><div><b><?= e($a['materia']) ?> · <?= e($a['assunto']) ?></b><small><?= e($sn) ?></small></div><strong class="<?= (int)$a['avaliavel']?((int)$a['correta']?'result-ok':'result-no'):'result-pending' ?>"><?= !(int)$a['avaliavel']?'Pendente':((int)$a['correta']?'Certa':'Errada') ?></strong></a><?php endforeach;?></section>
    <?php include __DIR__.'/includes/footer.php';exit;
}

$qtd=(int)($_GET['qtd']??10);if(!in_array($qtd,[5,10,15,20,30,40,50],true))$qtd=10;
$modo=(string)($_GET['modo']??'personalizado');if(!in_array($modo,['personalizado','inteligente','prova'],true))$modo='personalizado';
$concurso=(string)($_GET['concurso']??($modo==='prova'&&$user?(string)$user['foco']:''));if(!in_array($concurso,CONCURSOS_TODOS,true))$concurso='';
$materia=(string)($_GET['materia']??'');if(!in_array($materia,MATERIAS,true))$materia='';
$dif=(string)($_GET['dificuldade']??'');if(!in_array($dif,DIFICULDADES,true))$dif='';
$gerar=isset($_GET['gerar']);$lista=[];$execId=0;$intelligenceNote='';
if($gerar){
    if(!$uid){flash('info','Entre para gerar e salvar seu simulado.');redirect('login.php?next='.urlencode($_SERVER['REQUEST_URI']));}
    if($modo==='prova'&&$concurso!=='')$lista=sim_balanced($pdo,$concurso,$qtd,$uid);
    elseif($modo==='inteligente'){
        $weak=user_weak_topics($uid,4);$mastery=user_mastery($uid);usort($mastery,static fn($a,$b)=>$a['score']<=>$b['score']);
        $targets=[];foreach($weak as $w)$targets[]=(string)$w['materia'];foreach($mastery as $m)$targets[]=(string)$m['materia'];$targets=array_values(array_unique($targets));if(!$targets)$targets=['Matemática'];
        $bucket=[];$per=max(3,(int)ceil($qtd/min(4,count($targets))));foreach(array_slice($targets,0,4) as $m){foreach(sim_pick($pdo,['q.ativo=1','q.materia=?'],[$m],$per,true,$uid) as $q)$bucket[(int)$q['id']]=$q;}
        if(count($bucket)<$qtd)foreach(sim_pick($pdo,['q.ativo=1'],[],$qtd*2,true,$uid) as $q){$bucket[(int)$q['id']]=$q;if(count($bucket)>=$qtd)break;}
        $lista=array_values($bucket);shuffle($lista);$lista=array_slice($lista,0,$qtd);$intelligenceNote='Selecionado a partir dos seus pontos fracos e de questões ainda não respondidas.';
    }else{
        $where=['q.ativo=1'];$params=[];if($concurso!==''){$where[]='q.concurso=?';$params[]=$concurso;}if($materia!==''){$where[]='q.materia=?';$params[]=$materia;}if($dif!==''){$where[]='q.dificuldade=?';$params[]=$dif;}
        $lista=sim_pick($pdo,$where,$params,$qtd,true,$uid);if(count($lista)<$qtd)$lista=sim_pick($pdo,$where,$params,$qtd,false,$uid);
    }
    if($uid&&$lista){$ids=array_map('intval',array_column($lista,'id'));$pdo->prepare('INSERT INTO simulados_execucoes (user_id,modo,concurso,materia,qtd,ids_json,config_json,inicio,respondidas,avaliadas,acertos) VALUES (?,?,?,?,?,?,?,?,0,0,0)')->execute([$uid,$modo,$concurso,$materia,count($ids),json_encode($ids),json_encode(['dificuldade'=>$dif],JSON_UNESCAPED_UNICODE),now_str()]);$execId=(int)$pdo->lastInsertId();$_SESSION['simulado_atual']=['id'=>$execId,'ids'=>$ids];}
}
$history=[];if($uid){$st=$pdo->prepare('SELECT * FROM simulados_execucoes WHERE user_id=? ORDER BY id DESC LIMIT 6');$st->execute([$uid]);$history=$st->fetchAll();}
$title='Simulados';$active='simulados';include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">PRÁTICA SOB PRESSÃO</span><h1>Simulados</h1><p class="muted">Escolha entre bateria personalizada, seleção inteligente ou um modo de prova equilibrado pelo acervo do concurso.</p></div></div>
<div class="sim-mode-grid">
  <a class="sim-mode-card <?= $modo==='personalizado'?'active':'' ?>" href="<?= e(url('simulados.php?modo=personalizado')) ?>"><span>01</span><h3>Personalizado</h3><p>Você escolhe concurso, matéria, dificuldade e quantidade.</p></a>
  <a class="sim-mode-card <?= $modo==='inteligente'?'active':'' ?>" href="<?= e(url('simulados.php?modo=inteligente')) ?>"><span>02</span><h3>Inteligente</h3><p>Prioriza pontos fracos e questões ainda não resolvidas.</p></a>
  <a class="sim-mode-card <?= $modo==='prova'?'active':'' ?>" href="<?= e(url('simulados.php?modo=prova')) ?>"><span>03</span><h3>Modo prova</h3><p>Distribui as questões entre as disciplinas disponíveis do concurso.</p></a>
</div>
<div class="sim-builder"><div class="card"><h2>Configuração</h2><form method="get" class="form"><input type="hidden" name="gerar" value="1"><input type="hidden" name="modo" value="<?= e($modo) ?>"><div class="sim-options"><label>Quantidade<select name="qtd"><?php foreach([5,10,15,20,30,40,50] as $n):?><option value="<?= $n ?>" <?= $qtd===$n?'selected':'' ?>><?= $n ?> questões</option><?php endforeach;?></select></label><label>Concurso<select name="concurso"><option value="">Todos</option><?php foreach(CONCURSOS_TODOS as $c):if($c==='Outro')continue;?><option value="<?= e($c) ?>" <?= $concurso===$c?'selected':'' ?>><?= e(concurso_nome($c)) ?></option><?php endforeach;?></select></label><?php if($modo==='personalizado'):?><label>Disciplina<select name="materia"><option value="">Todas</option><?php foreach(MATERIAS as $m):if($m==='Geral')continue;?><option value="<?= e($m) ?>" <?= $materia===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach;?></select></label><label>Dificuldade<select name="dificuldade"><option value="">Todas</option><?php foreach(DIFICULDADES as $d):?><option <?= $dif===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach;?></select></label><?php endif;?></div><button class="btn btn-primary" type="submit">Montar simulado</button></form></div><aside class="sim-summary"><div class="sim-kpi"><?= $gerar?count($lista):$qtd ?></div><h2>questões</h2><p><?= $modo==='inteligente'?'O algoritmo usa seu histórico real para escolher a bateria.':($modo==='prova'?'A seleção é equilibrada entre as matérias existentes no acervo do concurso.':'Monte uma bateria objetiva para um conteúdo específico.') ?></p></aside></div>
<?php if($gerar):?><div class="card sim-ready"><div><span class="eyebrow">SIMULADO PRONTO</span><h2><?= count($lista) ?> questões selecionadas</h2><p class="muted"><?= e($intelligenceNote) ?></p></div><?php if($lista):?><a class="btn btn-primary" href="<?= e(url('resolver.php?id='.(int)$lista[0]['id'].'&sim_id='.$execId.'&pos=0')) ?>">Começar agora</a><?php endif;?></div><?php endif;?>
<?php if($history):?><section class="card"><div class="section-head"><div><span class="eyebrow">HISTÓRICO</span><h2>Últimos simulados</h2></div></div><div class="sim-history"><?php foreach($history as $h):$p=(int)$h['avaliadas']?(int)round((int)$h['acertos']*100/(int)$h['avaliadas']):0;?><a href="<?= e(url('simulados.php?resultado='.(int)$h['id'])) ?>"><span><b>#<?= (int)$h['id'] ?> · <?= e(ucfirst((string)$h['modo'])) ?></b><small><?= e(fmt_data((string)$h['inicio'])) ?> · <?= e((string)$h['concurso']?:'misto') ?></small></span><strong><?= (int)$h['respondidas'] ?>/<?= (int)$h['qtd'] ?> · <?= (int)$h['avaliadas']?$p.'%':'—' ?></strong></a><?php endforeach;?></div></section><?php endif;?>
<?php include __DIR__.'/includes/footer.php'; ?>
