<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=require_login();$uid=(int)$user['id'];$pdo=db();
$qctx=null;$qidCtx=(int)($_GET['q']??0);if($qidCtx>0){$st=$pdo->prepare('SELECT * FROM questoes WHERE id=? AND ativo=1');$st->execute([$qidCtx]);$qctx=$st->fetch()?:null;}
$st=$pdo->prepare('SELECT * FROM ia_perguntas WHERE user_id=? ORDER BY id DESC LIMIT 12');$st->execute([$uid]);$hist=array_reverse($st->fetchAll());
$mastery=user_mastery($uid);usort($mastery,static fn($a,$b)=>$a['score']<=>$b['score']);$weak=$mastery[0]??null;$due=review_due_count($uid);$week=user_week_progress($uid);
$title='Tutor IA';$active='';include __DIR__.'/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Tutor IA</h1>
    <p class="muted">Converse com a IA para analisar erros, montar um bloco de estudo e explicar questões. A Groq é a provedora principal; o Gemini entra apenas como reserva.</p>
  </div>
</div>
<div class="ai-layout ai-layout-compact">
  <section class="card ai-chat-card">
    <?php if(!ai_has_key()):?><div class="flash info" style="display:block">Sem chave ativa no momento. O tutor ainda mostra seu contexto de estudo, mas as respostas automáticas exigem uma chave Groq ou Gemini.</div><?php endif;?>
    <?php if($qctx):?><div class="ai-context-bar"><b>Questão em contexto</b><span><?= e(concurso_nome((string)$qctx['concurso'])) ?> <?= (int)$qctx['ano'] ?> · <?= e($qctx['materia']) ?></span><a href="<?= e(url('resolver.php?id='.(int)$qctx['id'])) ?>">abrir</a></div><?php endif;?>
    <div class="chat" id="iaLog"><?php if(!$hist):?><div class="msg ia">Posso resumir seus pontos fracos, explicar uma questão, sugerir uma sessão de estudo ou montar uma revisão para hoje.</div><?php endif;?><?php foreach($hist as $h):?><div class="msg user"><?= e($h['pergunta']) ?></div><div class="msg ia"><?= $h['resposta'] ?></div><?php endforeach;?></div>
    <div class="ai-prompt-chips"><button type="button" data-ia-prompt="Analise meu desempenho e diga o que estudar hoje.">Analisar desempenho</button><button type="button" data-ia-prompt="Monte uma sessão de estudo de 2 horas para hoje.">Sessão de 2 horas</button><button type="button" data-ia-prompt="Identifique os padrões dos meus erros e proponha correções.">Padrões de erro</button></div>
    <form class="chat-form" id="iaForm" data-qid="<?= $qctx?(int)$qctx['id']:'' ?>"><input id="iaInput" placeholder="Ex.: analise meus últimos erros em Física..." autocomplete="off"><button class="btn btn-primary" type="submit">Enviar</button></form>
  </section>
  <aside class="card ai-coach-side compact-side">
    <span class="eyebrow">RESUMO</span>
    <h2>Seu contexto</h2>
    <div class="coach-metric"><span>Matéria mais fraca</span><strong><?= $weak?e($weak['materia']).' · '.$weak['score'].'%':'Ainda sem dados' ?></strong></div>
    <div class="coach-metric"><span>Revisões pendentes</span><strong><?= $due ?></strong></div>
    <div class="coach-metric"><span>Questões na semana</span><strong><?= $week['questoes'] ?></strong></div>
    <div class="coach-metric"><span>Horas na semana</span><strong><?= number_format($week['horas'],1,',','.') ?></strong></div>
  </aside>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
