<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
require_once __DIR__ . '/includes/learning_content.php';
try { ensure_learning_content_current(db()); } catch (Throwable $e) {}

if (!$user) {
    $title = 'Sua preparação para concursos militares';
    $active = 'inicio';
    include __DIR__ . '/includes/header.php';
    $st = db()->query('SELECT COUNT(*) FROM questoes WHERE ativo = 1');
    $nq = (int)$st->fetchColumn();
?>
<section class="hero hero-clean">
  <div class="hero-kicker">PREPARAÇÃO MILITAR EM UM SÓ LUGAR</div>
  <h1>Papiro <b>Máximo</b></h1>
  <p>Questões oficiais, trilhas por concurso, controle de horas, simulados, caderno de erros e assistência por IA para organizar uma preparação consistente.</p>
  <div class="hero-actions"><a class="btn btn-gold" href="<?= e(url('cadastro.php')) ?>">Criar conta</a><a class="btn btn-ghost" href="<?= e(url('questoes.php')) ?>">Explorar <?= number_format($nq,0,',','.') ?> questões</a></div>
</section>
<div class="features features-clean">
  <div class="feature"><div class="feature-mark">01</div><h3>Banco de questões</h3><p class="muted">Filtros por prova, ano, disciplina e dificuldade, com enunciado legível e apoio visual quando necessário.</p></div>
  <div class="feature"><div class="feature-mark">02</div><h3>Trilhas por concurso</h3><p class="muted">Sequência detalhada de conteúdos, metas de domínio, prática e critérios para avançar.</p></div>
  <div class="feature"><div class="feature-mark">03</div><h3>Métricas de estudo</h3><p class="muted">Horas, questões, precisão, distribuição por disciplina e evolução nas últimas semanas.</p></div>
  <div class="feature"><div class="feature-mark">04</div><h3>Caderno de erros</h3><p class="muted">Transforme cada erro em revisão dirigida e use o histórico para decidir o próximo foco.</p></div>
</div>
<?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$title = 'Início';
$active = 'inicio';
$uid = (int)$user['id'];
$stats = user_stats($uid);
$goals = user_goals($uid);
$week = user_week_progress($uid);
$mastery = user_mastery($uid);
$dueReviews = review_due_count($uid);
$todayPlan = ensure_daily_plan($uid);
$masteryJson = json_encode(array_map(static fn($m)=>['m'=>$m['materia'],'score'=>$m['score'],'status'=>$m['status']], array_slice($mastery,0,6)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$st = db()->prepare('SELECT * FROM trilhas WHERE (slug = ? OR nome = ?) AND ativo = 1 LIMIT 1');
$focoSlug = strtolower($user['foco'] === 'CN' ? 'cn' : ($user['foco'] === 'Outro' ? 'base' : $user['foco']));
$st->execute([$focoSlug, $user['foco']]);
$trilha = $st->fetch();
if (!$trilha) $trilha = db()->query("SELECT * FROM trilhas WHERE slug = 'base' LIMIT 1")->fetch();
[$tTotal, $tFeitos, $tPct] = $trilha ? trilha_progresso($uid, (int)$trilha['id']) : [0,0,0];

$weakest = null;
foreach ($stats['materias'] as $m) {
    $avaliadas = (int)$m['t'];
    if ($avaliadas < 3) continue;
    $pct = (int)round((int)$m['ok'] * 100 / max(1,$avaliadas));
    if ($weakest === null || $pct < $weakest['pct']) $weakest = ['materia'=>(string)$m['m'],'pct'=>$pct,'n'=>(int)$m['n']];
}
$subjectJson = json_encode(array_map(static fn($m)=>['m'=>(string)$m['m'],'n'=>(int)$m['n']], $stats['materias']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$todayQ = 0; $todaySec = 0;
$todayKey = date('Y-m-d');
if (isset($stats['dias'][$todayKey])) { $todayQ=(int)$stats['dias'][$todayKey]['q']; $todaySec=(int)$stats['dias'][$todayKey]['s']; }

include __DIR__ . '/includes/header.php';
?>
<div class="dashboard-head">
  <div>
    <div class="eyebrow">PAINEL DE ESTUDOS</div>
    <h1><?= e(explode(' ', trim((string)$user['nome']))[0]) ?>, acompanhe seu ritmo.</h1>
    <p class="muted">Foco atual: <b><?= e(concurso_nome((string)$user['foco'])) ?></b>. Use os dados abaixo para decidir onde colocar a próxima hora de estudo.</p>
  </div>
  <div class="dashboard-actions"><a class="btn" href="<?= e(url('plano.php')) ?>">Plano de hoje</a><a class="btn" href="<?= e(url('trilha.php?slug=' . ($trilha['slug'] ?? 'base'))) ?>">Continuar trilha</a><a class="btn btn-primary" href="<?= e(url('questoes.php?concurso='.urlencode((string)$user['foco']))) ?>">Resolver questões</a></div>
</div>

<div class="dashboard-kpis">
  <div class="kpi-card"><span>Tempo acumulado</span><strong><?= e(fmt_duracao($stats['segundos'])) ?></strong><small><?= e(fmt_duracao($todaySec)) ?> hoje</small></div>
  <div class="kpi-card"><span>Questões resolvidas</span><strong><?= number_format($stats['distintas'],0,',','.') ?></strong><small><?= $todayQ ?> tentativa(s) hoje</small></div>
  <div class="kpi-card"><span>Precisão validada</span><strong><?= $stats['taxa'] ?>%</strong><small><?= (int)$stats['avaliadas'] ?> tentativa(s) com gabarito</small></div>
  <div class="kpi-card accent"><span>Ofensiva atual</span><strong><?= $stats['ofensiva'] ?> dias</strong><small>atividade consecutiva</small></div>
</div>

<div class="card study-timer-card">
  <div class="timer-copy"><span class="eyebrow">SESSÃO DE ESTUDO</span><div class="timer-display" id="timerDisplay">00:00:00</div><div class="muted" id="timerStatus">Use o cronômetro para alimentar seus gráficos de tempo.</div></div>
  <div class="timer-controls"><button class="btn btn-primary" id="timerStart">Iniciar sessão</button><button class="btn" id="timerStop">Encerrar</button></div>
</div>

<div class="dashboard-grid dashboard-grid-main">
  <section class="card chart-card activity-card">
    <div class="section-head"><div><span class="eyebrow">ATIVIDADE</span><h2>Ritmo de estudo</h2></div><div class="chart-tabs"><button class="tab active" data-days="7">7 dias</button><button class="tab" data-days="30">30 dias</button></div></div>
    <div class="chart-wrap"><canvas class="chart" id="chartMain" data-chart="<?= e(json_encode($stats['dias'])) ?>"></canvas></div>
    <div class="chart-caption"><span><i class="legend-swatch legend-primary"></i> questões por dia</span><span><i class="legend-line"></i> horas estudadas</span></div>
  </section>

  <section class="card chart-card subject-card">
    <div class="section-head"><div><span class="eyebrow">DISTRIBUIÇÃO</span><h2>Questões por matéria</h2></div></div>
    <?php if(!$stats['materias']): ?><div class="empty-state">Resolva questões para construir este gráfico.</div><?php else: ?>
      <div class="donut-layout"><canvas id="chartSubjects" data-chart="<?= e($subjectJson ?: '[]') ?>" aria-label="Distribuição de questões por matéria"></canvas><div class="donut-legend" id="subjectLegend"></div></div>
    <?php endif; ?>
  </section>
</div>

<div class="dashboard-grid dashboard-grid-secondary">
  <section class="card">
    <div class="section-head"><div><span class="eyebrow">PRECISÃO</span><h2>Desempenho por matéria</h2></div><a class="text-link" href="<?= e(url('questoes.php')) ?>">Abrir banco</a></div>
    <?php if(!$stats['materias']): ?><p class="muted">Ainda não há respostas registradas.</p><?php else: ?>
      <div class="performance-list">
      <?php foreach(array_slice($stats['materias'],0,8) as $m): $mt=(int)$m['t']; $mp=$mt>0?(int)round((int)$m['ok']*100/$mt):0; ?>
        <div class="performance-row">
          <div class="performance-label"><b><?= e((string)$m['m']) ?></b><small><?= (int)$m['n'] ?> resolvidas<?= $mt>0?' · '.$mt.' avaliadas':' · aguardando gabarito' ?></small></div>
          <div class="performance-meter"><span style="width:<?= $mt>0?$mp:0 ?>%"></span></div>
          <strong><?= $mt>0?$mp.'%':'—' ?></strong>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card today-plan">
    <div class="section-head"><div><span class="eyebrow">PRÓXIMO PASSO</span><h2>Plano de hoje</h2></div></div>
    <div class="plan-list">
      <div class="plan-item"><span class="plan-num">1</span><div><b>Faça 35–50 minutos de teoria ativa</b><p><?= $weakest ? 'Prioridade sugerida: '.e($weakest['materia']).' ('.$weakest['pct'].'% nas tentativas avaliadas).' : 'Use o próximo módulo da sua trilha como assunto principal.' ?></p></div></div>
      <div class="plan-item"><span class="plan-num">2</span><div><b>Resolva uma bateria curta</b><p>Meta: 15 questões do assunto estudado, marcando dúvidas e justificando os erros.</p></div></div>
      <div class="plan-item"><span class="plan-num">3</span><div><b>Feche com revisão</b><p>Reveja o caderno de erros por 15 minutos e registre uma regra prática para cada erro repetido.</p></div></div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('questoes.php'.($weakest?'?materia='.urlencode($weakest['materia']):''))) ?>">Começar bateria</a>
  </section>
</div>

<div class="dashboard-grid dashboard-grid-secondary">
  <section class="card">
    <div class="section-head"><div><span class="eyebrow">DOMÍNIO</span><h2>Radar de matérias</h2></div><a class="text-link" href="<?= e(url('plano.php')) ?>">Ver plano</a></div>
    <?php if(!$mastery): ?><p class="muted">Resolva questões avaliáveis para formar seu radar.</p><?php else: ?><div class="radar-layout"><canvas id="chartMastery" data-chart="<?= e($masteryJson ?: '[]') ?>"></canvas><div class="radar-copy"><b>Como ler</b><p>O índice combina precisão com volume de prática. Uma matéria só chega perto de 100% quando você mantém desempenho e repertório.</p></div></div><?php endif; ?>
  </section>
  <section class="card">
    <div class="section-head"><div><span class="eyebrow">META SEMANAL</span><h2>Você está no ritmo?</h2></div><a class="text-link" href="<?= e(url('plano.php')) ?>">Ajustar metas</a></div>
    <?php $gh=min(100,(int)round($week['horas']*100/max(.1,(float)$goals['horas_semana'])));$gq=min(100,(int)round($week['questoes']*100/max(1,(int)$goals['questoes_semana'])));$gs=min(100,(int)round($week['simulados']*100/max(1,(int)$goals['simulados_semana']))); ?>
    <div class="weekly-mini-goals"><div><span>Horas</span><strong><?= number_format($week['horas'],1,',','.') ?>/<?= number_format((float)$goals['horas_semana'],1,',','.') ?></strong><div class="progress"><div class="progress-bar" style="width:<?= $gh ?>%"></div></div></div><div><span>Questões</span><strong><?= $week['questoes'] ?>/<?= (int)$goals['questoes_semana'] ?></strong><div class="progress"><div class="progress-bar" style="width:<?= $gq ?>%"></div></div></div><div><span>Simulados</span><strong><?= $week['simulados'] ?>/<?= (int)$goals['simulados_semana'] ?></strong><div class="progress"><div class="progress-bar" style="width:<?= $gs ?>%"></div></div></div></div>
    <div class="review-alert <?= $dueReviews?'has-due':'' ?>"><div><b><?= $dueReviews ?> revisão(ões) vencendo</b><span><?= $dueReviews?'Revisar hoje evita reaprender do zero depois.':'Seu caderno está em dia.' ?></span></div><a class="btn btn-small" href="<?= e(url('caderno.php?rev=hoje')) ?>">Abrir revisões</a></div>
  </section>
</div>

<div class="card home-plan-card">
  <div class="section-head"><div><span class="eyebrow">PLANO AUTOMÁTICO</span><h2>As próximas ações de hoje</h2></div><a class="text-link" href="<?= e(url('plano.php')) ?>">Abrir plano completo</a></div>
  <div class="home-plan-items"><?php foreach(array_slice($todayPlan,0,5) as $item): ?><a href="<?= e(url((string)$item['link'])) ?>" class="<?= (int)$item['concluido']?'done':'' ?>"><span><?= e(mb_strtoupper((string)$item['tipo'])) ?></span><div><b><?= e($item['titulo']) ?></b><small><?= e($item['descricao']) ?></small></div><strong><?= (int)$item['concluido']?'Feito':'Abrir' ?></strong></a><?php endforeach; ?></div>
</div>

<div class="dashboard-grid dashboard-grid-secondary">
  <section class="card trail-summary">
    <div class="section-head"><div><span class="eyebrow">TRILHA</span><h2><?= $trilha ? e((string)$trilha['nome']) : 'Base de estudos' ?></h2></div><strong><?= $tPct ?>%</strong></div>
    <p class="muted"><?= $tFeitos ?> de <?= $tTotal ?> módulos concluídos.</p>
    <div class="progress"><div class="progress-bar" id="trilhaBar" style="width:<?= $tPct ?>%"></div></div>
    <div class="trail-summary-actions"><a class="btn" href="<?= e(url('trilha.php?slug='.($trilha['slug']??'base'))) ?>">Abrir trilha detalhada</a><a class="text-link" href="<?= e(url('guia.php')) ?>">Como estudar melhor</a></div>
  </section>

  <section class="card">
    <div class="section-head"><div><span class="eyebrow">PROVAS</span><h2>Precisão por concurso</h2></div></div>
    <?php if(!$stats['concursos']): ?><p class="muted">Resolva sua primeira questão para ver a comparação.</p><?php else: ?>
      <?php foreach(array_slice($stats['concursos'],0,7) as $c): $ct=(int)$c['t'];$pct=$ct>0?(int)round((int)$c['ok']*100/$ct):0; ?>
        <div class="bar-row"><span><?= e(concurso_nome((string)$c['c'])) ?></span><div class="bar"><div class="bar-fill" style="width:<?= $ct>0?$pct:0 ?>%"></div></div><b><?= $ct>0?$pct.'%':'—' ?></b></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
