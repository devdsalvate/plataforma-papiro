<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user=require_login(); $uid=(int)$user['id']; $pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['csrf']??null)){flash('error','Sessão expirada.');redirect('plano.php');}
    $form=(string)($_POST['form']??'');
    if($form==='metas'){
        $h=max(1,min(80,(float)($_POST['horas']??10)));
        $q=max(20,min(2000,(int)($_POST['questoes']??150)));
        $s=max(0,min(14,(int)($_POST['simulados']??1)));
        $st=$pdo->prepare('SELECT 1 FROM metas_usuario WHERE user_id=?');$st->execute([$uid]);
        if($st->fetchColumn())$pdo->prepare('UPDATE metas_usuario SET horas_semana=?,questoes_semana=?,simulados_semana=?,updated_at=? WHERE user_id=?')->execute([$h,$q,$s,now_str(),$uid]);
        else $pdo->prepare('INSERT INTO metas_usuario (horas_semana,questoes_semana,simulados_semana,updated_at,user_id) VALUES (?,?,?,?,?)')->execute([$h,$q,$s,now_str(),$uid]);
        flash('success','Metas semanais atualizadas.');redirect('plano.php');
    }
    if($form==='regenerar'){
        $pdo->prepare('DELETE FROM plano_diario WHERE user_id=? AND dia=?')->execute([$uid,today_str()]);
        flash('info','Plano de hoje recalculado com seus dados atuais.');redirect('plano.php');
    }
}

$plan=ensure_daily_plan($uid); $goals=user_goals($uid); $week=user_week_progress($uid); $mastery=user_mastery($uid); $weak=user_weak_topics($uid,6); $due=review_due_count($uid);
$done=count(array_filter($plan,static fn($x)=>(int)$x['concluido']===1)); $pct=count($plan)?(int)round($done*100/count($plan)):0;
$title='Plano diário';$active='plano';include __DIR__.'/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">PLANEJAMENTO ADAPTATIVO</span><h1>Plano de hoje</h1><p class="muted">Gerado a partir do seu desempenho, revisões vencendo, trilha e metas semanais.</p></div><div class="plan-score"><strong><?= $pct ?>%</strong><span><?= $done ?>/<?= count($plan) ?> concluídos</span></div></div>

<div class="plan-hero-grid">
  <section class="card">
    <div class="section-head"><div><span class="eyebrow">EXECUÇÃO</span><h2><?= e(date('d/m/Y')) ?></h2></div><form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="regenerar"><button class="btn btn-small" type="submit">Recalcular plano</button></form></div>
    <div class="progress"><div class="progress-bar" id="planBar" style="width:<?= $pct ?>%"></div></div>
    <div class="daily-plan-list" id="dailyPlanList">
      <?php foreach($plan as $item): ?>
      <article class="daily-plan-item <?= (int)$item['concluido']?'done':'' ?>" data-plan-row="<?= (int)$item['id'] ?>">
        <label class="plan-check"><input type="checkbox" data-plan-toggle="<?= (int)$item['id'] ?>" <?= (int)$item['concluido']?'checked':'' ?>><span></span></label>
        <div class="plan-item-body"><div class="plan-kind"><?= e(mb_strtoupper((string)$item['tipo'])) ?></div><h3><?= e($item['titulo']) ?></h3><p><?= e($item['descricao']) ?></p><?php if($item['link']!==''): ?><a class="text-link" href="<?= e(url($item['link'])) ?>">Abrir atividade →</a><?php endif; ?></div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="card weekly-goals-card">
    <span class="eyebrow">META DA SEMANA</span><h2>Consistência antes de volume</h2>
    <?php $hp=min(100,(int)round($week['horas']*100/max(.1,(float)$goals['horas_semana'])));$qp=min(100,(int)round($week['questoes']*100/max(1,(int)$goals['questoes_semana'])));$sp=min(100,(int)round($week['simulados']*100/max(1,(int)$goals['simulados_semana']))); ?>
    <div class="goal-row"><div><b>Horas</b><span><?= number_format($week['horas'],1,',','.') ?> / <?= number_format((float)$goals['horas_semana'],1,',','.') ?> h</span></div><div class="progress"><div class="progress-bar" style="width:<?= $hp ?>%"></div></div></div>
    <div class="goal-row"><div><b>Questões</b><span><?= $week['questoes'] ?> / <?= (int)$goals['questoes_semana'] ?></span></div><div class="progress"><div class="progress-bar" style="width:<?= $qp ?>%"></div></div></div>
    <div class="goal-row"><div><b>Simulados</b><span><?= $week['simulados'] ?> / <?= (int)$goals['simulados_semana'] ?></span></div><div class="progress"><div class="progress-bar" style="width:<?= $sp ?>%"></div></div></div>
    <details class="goal-editor"><summary>Ajustar metas</summary><form method="post" class="form compact-form"><?= csrf_field() ?><input type="hidden" name="form" value="metas"><label>Horas/semana<input type="number" step="0.5" min="1" max="80" name="horas" value="<?= e((string)$goals['horas_semana']) ?>"></label><label>Questões/semana<input type="number" min="20" max="2000" name="questoes" value="<?= (int)$goals['questoes_semana'] ?>"></label><label>Simulados/semana<input type="number" min="0" max="14" name="simulados" value="<?= (int)$goals['simulados_semana'] ?>"></label><button class="btn btn-primary" type="submit">Salvar metas</button></form></details>
  </aside>
</div>

<div class="dashboard-grid dashboard-grid-secondary">
  <section class="card"><div class="section-head"><div><span class="eyebrow">DOMÍNIO</span><h2>Mapa de matérias</h2></div><a class="text-link" href="<?= e(url('questoes.php')) ?>">Praticar</a></div>
    <?php if(!$mastery): ?><p class="muted">Resolva algumas questões avaliáveis para formar seu mapa.</p><?php else: ?><div class="mastery-list"><?php foreach($mastery as $m): ?><div class="mastery-row"><div><b><?= e($m['materia']) ?></b><small><?= e($m['status']) ?> · <?= $m['precisao'] ?>% precisão</small></div><div class="mastery-meter"><span class="mastery-<?= e($m['class']) ?>" style="width:<?= $m['score'] ?>%"></span></div><strong><?= $m['score'] ?>%</strong></div><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="card"><div class="section-head"><div><span class="eyebrow">REVISÃO</span><h2>O que merece atenção</h2></div><span class="badge <?= $due?'b-red':'b-green' ?>"><?= $due ?> vencendo</span></div>
    <?php if(!$weak): ?><p class="muted">Ainda não há assuntos com tentativas suficientes para detectar padrões.</p><?php else: ?><div class="weak-topic-list"><?php foreach($weak as $w): ?><a href="<?= e(url('questoes.php?materia='.urlencode($w['materia']).'&busca='.urlencode($w['assunto']))) ?>"><span><b><?= e($w['assunto']) ?></b><small><?= e($w['materia']) ?> · <?= $w['tentativas'] ?> tentativas</small></span><strong><?= $w['precisao'] ?>%</strong></a><?php endforeach; ?></div><?php endif; ?>
    <a class="btn btn-primary btn-block" href="<?= e(url('caderno.php?rev=hoje')) ?>">Abrir revisões do dia</a>
  </section>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
