<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();

if (!$user) {
    $title = 'Sua aprovação na carreira militar';
    $active = 'inicio';
    include __DIR__ . '/includes/header.php';
    $st = db()->query('SELECT COUNT(*) FROM questoes WHERE ativo = 1');
    $nq = (int)$st->fetchColumn();
?>
<div class="hero">
  <h1>📜 Papiro <b>Máximo</b></h1>
  <p>Sua plataforma de preparação para <b>EFOMM, EPCAR, EEAR, Colégio Naval e ITA</b>: <?= $nq ?> questões comentadas, trilhas por concurso, controle de horas, ofensiva de estudos, ranking, caderno de erros e IA que explica cada questão.</p>
  <a class="btn btn-gold" href="<?= e(url('cadastro.php')) ?>">🚀 Começar grátis</a>
  <a class="btn btn-ghost" href="<?= e(url('questoes.php')) ?>">Ver questões</a>
</div>
<div class="features">
  <div class="feature"><div class="ico">📝</div><h3>Banco de questões</h3><p class="muted">Filtros por concurso, ano, matéria e dificuldade, com resolução comentada.</p></div>
  <div class="feature"><div class="ico">🗺️</div><h3>Trilhas por concurso</h3><p class="muted">Saiba exatamente o que estudar, do zero ao avançado.</p></div>
  <div class="feature"><div class="ico">⏱️</div><h3>Horas + ofensiva 🔥</h3><p class="muted">Cronômetro de estudos e streak diário para manter a constância.</p></div>
  <div class="feature"><div class="ico">📓</div><h3>Caderno de erros</h3><p class="muted">Errou? Vai direto para o caderno com revisão espaçada.</p></div>
  <div class="feature"><div class="ico">🏆</div><h3>Grupos e ranking</h3><p class="muted">Compare sua constância com amigos e suba no ranking.</p></div>
  <div class="feature"><div class="ico">🤖</div><h3>IA que explica</h3><p class="muted">Resoluções passo a passo geradas por IA em cada questão.</p></div>
</div>
<?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- dashboard logado ----------
$title = 'Início';
$active = 'inicio';
$stats = user_stats((int)$user['id']);

// trilha do foco do usuário
$st = db()->prepare('SELECT * FROM trilhas WHERE (slug = ? OR nome = ?) AND ativo = 1 LIMIT 1');
$focoSlug = strtolower($user['foco'] === 'CN' ? 'cn' : ($user['foco'] === 'Outro' ? 'base' : $user['foco']));
$st->execute([$focoSlug, $user['foco']]);
$trilha = $st->fetch();
if (!$trilha) {
    $trilha = db()->query("SELECT * FROM trilhas WHERE slug = 'base' LIMIT 1")->fetch();
}
[$tTotal, $tFeitos, $tPct] = $trilha ? trilha_progresso((int)$user['id'], (int)$trilha['id']) : [0, 0, 0];

include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>Olá, <?= e(explode(' ', $user['nome'])[0]) ?>! 👋</h1>
  <span class="badge b-gold"><?= concurso_icone($user['foco']) ?> Foco: <?= e(concurso_nome($user['foco'])) ?></span>
  <span class="spacer"></span>
  <a class="btn btn-gold" href="<?= e(url('questoes.php?concurso=' . urlencode($user['foco']))) ?>">📝 Estudar agora</a>
</div>

<div class="card">
  <div class="timer">
    <div>
      <div class="muted">⏱️ Cronômetro de estudos</div>
      <div class="timer-display" id="timerDisplay">00:00:00</div>
      <div class="muted" id="timerStatus"></div>
    </div>
    <span style="flex:1"></span>
    <button class="btn btn-ok" id="timerStart">▶️ Iniciar</button>
    <button class="btn btn-danger" id="timerStop">⏹️ Encerrar</button>
  </div>
</div>

<div class="grid-stats">
  <div class="stat"><div class="stat-num"><?= e(fmt_duracao($stats['segundos'])) ?></div><div class="stat-label">⏱️ Horas estudadas</div></div>
  <div class="stat"><div class="stat-num"><?= $stats['distintas'] ?></div><div class="stat-label">📝 Questões resolvidas</div></div>
  <div class="stat"><div class="stat-num"><?= $stats['taxa'] ?>%</div><div class="stat-label">🎯 Taxa de acerto</div></div>
  <div class="stat gold"><div class="stat-num">🔥 <?= $stats['ofensiva'] ?></div><div class="stat-label">dias de ofensiva</div></div>
</div>

<div class="card">
  <div class="page-head" style="margin-bottom:8px">
    <h3 style="margin:0">📊 Meu ritmo</h3>
    <span class="spacer"></span>
    <button class="tab active" data-days="7">7 dias</button>
    <button class="tab" data-days="30">30 dias</button>
  </div>
  <div class="chart-wrap"><canvas class="chart" id="chartMain" data-chart="<?= e(json_encode($stats['dias'])) ?>"></canvas></div>
  <p class="muted">🟨 barras = questões/dia · ⬛ linha = horas/dia</p>
</div>

<div class="grid-2">
  <div class="card">
    <h3>🗺️ Progresso da trilha</h3>
    <?php if ($trilha): ?>
      <p><b><?= e($trilha['icone'] . ' ' . $trilha['nome']) ?></b> · <?= $tFeitos ?>/<?= $tTotal ?> módulos (<span id="trilhaPct"><?= $tPct ?>%</span>)</p>
      <div class="progress"><div class="progress-bar" id="trilhaBar" style="width:<?= $tPct ?>%"></div></div>
      <p style="margin-top:10px"><a class="btn btn-small" href="<?= e(url('trilha.php?slug=' . $trilha['slug'])) ?>">Continuar trilha →</a></p>
    <?php else: ?>
      <p class="muted">Nenhuma trilha ativa.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>🎯 Desempenho por concurso</h3>
    <?php if (!$stats['concursos']): ?>
      <p class="muted">Resolva sua primeira questão para ver seu desempenho aqui.</p>
    <?php else: ?>
      <?php foreach ($stats['concursos'] as $c):
          $pct = $c['t'] > 0 ? (int)round($c['ok'] * 100 / $c['t']) : 0; ?>
        <div class="bar-row">
          <span><?= concurso_icone($c['c']) ?> <?= e($c['c']) ?></span>
          <div class="bar"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
          <b><?= $pct ?>%</b>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
