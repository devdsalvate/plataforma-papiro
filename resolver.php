<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$uid = $user ? (int)$user['id'] : 0;
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND ativo = 1');
$st->execute([$id]);
$q = $st->fetch();
if (!$q) {
    $title = 'Questão não encontrada';
    $active = 'questoes';
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><p>😕 Questão não encontrada ou inativa. <a href="' . e(url('questoes.php')) . '">Voltar</a></p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// última tentativa + favorito + stats + comentários
$ultima = null;
$fav = false;
if ($uid) {
    $m = user_answers_map($uid, [(int)$q['id']]);
    $ultima = $m[(int)$q['id']] ?? null;
    $st = $pdo->prepare('SELECT 1 FROM favoritos WHERE user_id = ? AND questao_id = ?');
    $st->execute([$uid, $q['id']]);
    $fav = (bool)$st->fetchColumn();
}
$st = $pdo->prepare('SELECT COUNT(*) t, COALESCE(SUM(correta),0) c FROM respostas WHERE questao_id = ?');
$st->execute([$q['id']]);
$qs = $st->fetch();
$st = $pdo->prepare('SELECT c.*, u.nome FROM comentarios c JOIN users u ON u.id = c.user_id WHERE c.questao_id = ? AND c.aprovado = 1 ORDER BY c.id DESC LIMIT 30');
$st->execute([$q['id']]);
$comments = $st->fetchAll();

// próxima questão (mesmo concurso, não resolvida se logado)
if ($uid) {
    $st = $pdo->prepare('SELECT id FROM questoes WHERE ativo = 1 AND concurso = ? AND id != ? AND NOT EXISTS (SELECT 1 FROM respostas r WHERE r.user_id = ? AND r.questao_id = questoes.id) LIMIT 50');
    $st->execute([$q['concurso'], $q['id'], $uid]);
} else {
    $st = $pdo->prepare('SELECT id FROM questoes WHERE ativo = 1 AND concurso = ? AND id != ? LIMIT 50');
    $st->execute([$q['concurso'], $q['id']]);
}
$cand = $st->fetchAll(PDO::FETCH_COLUMN);
if (!$cand) {
    $st = $pdo->prepare('SELECT id FROM questoes WHERE ativo = 1 AND concurso = ? AND id != ? LIMIT 50');
    $st->execute([$q['concurso'], $q['id']]);
    $cand = $st->fetchAll(PDO::FETCH_COLUMN);
}
$nextId = $cand ? $cand[array_rand($cand)] : null;

$title = concurso_nome($q['concurso']) . ' ' . $q['ano'] . ' · ' . $q['materia'];
$active = 'questoes';
include __DIR__ . '/includes/header.php';
$alts = q_alternativas($q);
?>
<div class="page-head">
  <a class="btn btn-small" href="<?= e(url('questoes.php?concurso=' . urlencode($q['concurso']))) ?>">← Voltar</a>
  <h1 style="font-size:1.2rem"><?= concurso_icone($q['concurso']) ?> <?= e(concurso_nome($q['concurso'])) ?> · <?= (int)$q['ano'] ?></h1>
</div>

<div class="card" id="questaoBox" data-qid="<?= (int)$q['id'] ?>" data-answered="0">
  <div class="q-badges">
    <span class="badge b-gold"><?= e($q['materia']) ?></span>
    <span class="badge"><?= e($q['assunto']) ?></span>
    <span class="badge"><?= e($q['dificuldade']) ?></span>
    <?php if ((int)($q['pagina'] ?? 0) > 0): ?>
      <span class="badge" title="Página da questão no PDF original<?= ($q['regiao'] ?? '') === '~' ? ' (aproximada)' : '' ?>">📄 p. <?= ($q['regiao'] ?? '') === '~' ? '~' : '' ?><?= (int)$q['pagina'] ?></span>
    <?php endif; ?>
    <?php if ((int)$qs['t'] > 0): ?>
      <span class="badge b-blue">🎯 <?= (int)round($qs['c'] * 100 / max(1, $qs['t'])) ?>% de acerto (<?= (int)$qs['t'] ?> tentativas)</span>
    <?php endif; ?>
    <?php if ($ultima): ?>
      <span class="q-status"><?= $ultima['correta'] ? '<span class="badge b-green">✅ você acertou antes</span>' : '<span class="badge b-red">❌ você errou antes</span>' ?></span>
    <?php endif; ?>
  </div>

  <div class="enunciado"><?= $q['enunciado'] ?></div>
  <?php
  try {
      $stIm = db()->prepare('SELECT * FROM questao_imagens WHERE questao_id = ? ORDER BY id');
      $stIm->execute([(int)$q['id']]);
      $qimgs = $stIm->fetchAll();
  } catch (Throwable $e) { $qimgs = []; }
  foreach ($qimgs as $qi): ?>
    <figure style="margin:12px 0;text-align:center">
      <a href="<?= e(url('assets/uploads/questoes/' . $qi['arquivo'])) ?>" target="_blank"><img src="<?= e(url('assets/uploads/questoes/' . $qi['arquivo'])) ?>" alt="Figura da questão" style="max-width:100%;max-height:420px;border:1px solid var(--line);border-radius:12px"></a>
      <?php if (!empty($qi['legenda'])): ?><figcaption class="muted"><?= e($qi['legenda']) ?></figcaption><?php endif; ?>
    </figure>
  <?php endforeach; ?>
  <?php
  // Sem figura extraída mas com região mapeada? Recorte automático da questão (p/ gráficos vetoriais).
  $pdfCrop = null;
  if (empty($qimgs) && !empty($q['origem']) && !empty($q['regiao']) && (int)($q['pagina'] ?? 0) > 0
      && preg_match('/^(\d*\.?\d+)-(\d*\.?\d+)$/', (string)$q['regiao'], $mRg)) {
      $pdfBase = basename((string)$q['origem']);
      if (strtolower(substr($pdfBase, -4)) === '.pdf' && is_file(APP_ROOT . '/simulados/' . $pdfBase)) {
          $pdfCrop = ['url' => url('simulados/' . rawurlencode($pdfBase)), 'pagina' => (int)$q['pagina'], 'y0' => (float)$mRg[1], 'y1' => (float)$mRg[2]];
      }
  }
  if ($pdfCrop && $pdfCrop['y1'] > $pdfCrop['y0']): ?>
    <figure class="pdfcropbox" data-pdf="<?= e($pdfCrop['url']) ?>" data-page="<?= (int)$pdfCrop['pagina'] ?>" data-y0="<?= $pdfCrop['y0'] ?>" data-y1="<?= $pdfCrop['y1'] ?>" style="margin:12px 0;text-align:center">
      <canvas class="pdfcrop" style="max-width:100%;height:auto;border:1px solid var(--line);border-radius:12px"></canvas>
      <figcaption class="muted">📷 Figura da questão — recorte automático (p. <?= (int)$pdfCrop['pagina'] ?>)</figcaption>
    </figure>
    <script type="module" src="<?= e(url('assets/js/pdfprint.js')) ?>"></script>
  <?php endif; ?>
  <?php
  $printImg = null;
  if (!empty($q['origem']) && (int)($q['pagina'] ?? 0) > 0) {
      try {
          $stPp = db()->prepare('SELECT arquivo_img FROM import_paginas WHERE arquivo = ? AND pagina = ?');
          $stPp->execute([$q['origem'], (int)$q['pagina']]);
          $printImg = $stPp->fetchColumn() ?: null;
      } catch (Throwable $e) { $printImg = null; }
  }
  if ($printImg): ?>
    <details style="margin:12px 0;border:1px dashed #b9ad86;border-radius:12px;padding:10px 14px;background:#f7f2e2">
      <summary style="cursor:pointer;font-weight:700">📄 Ver print da página original (p. <?= (int)$q['pagina'] ?>) — pega TUDO, inclusive fórmulas</summary>
      <a href="<?= e(url('assets/uploads/paginas/' . $printImg)) ?>" target="_blank"><img src="<?= e(url('assets/uploads/paginas/' . $printImg)) ?>" alt="Print da página <?= (int)$q['pagina'] ?>" style="max-width:100%;border:1px solid var(--line);border-radius:10px;margin-top:8px"></a>
    </details>
  <?php endif; ?>
  <?php
  // Sem PNG? O site gera o print da página sozinho no navegador (PDF.js local) — sem renderizador no servidor.
  $pdfVer = null;
  if (!$printImg && !empty($q['origem'])) {
      $pdfBase = basename((string)$q['origem']);
      if (strtolower(substr($pdfBase, -4)) === '.pdf' && is_file(APP_ROOT . '/simulados/' . $pdfBase)) {
          $pdfVer = ['url' => url('simulados/' . rawurlencode($pdfBase)), 'pagina' => max(1, (int)($q['pagina'] ?? 0)), 'desconhecida' => ((int)($q['pagina'] ?? 0) <= 0)];
      }
  }
  if ($pdfVer): ?>
    <details style="margin:12px 0;border:1px dashed #b9ad86;border-radius:12px;padding:10px 14px;background:#f7f2e2">
      <summary style="cursor:pointer;font-weight:700">📄 <?= !empty($pdfVer['desconhecida']) ? 'Ver o PDF original (página não identificada — abrindo na p. 1)' : ('Ver print da página original (p. ' . (int)$pdfVer['pagina'] . ') — o site gera sozinho, pega TUDO') ?></summary>
      <div class="pdfprint" data-pdf="<?= e($pdfVer['url']) ?>" data-page="<?= (int)$pdfVer['pagina'] ?>">
        <p class="pdfprint-status muted" style="margin:8px 0">🖨️ Gerando print da página <?= (int)$pdfVer['pagina'] ?>...</p>
        <canvas style="display:none;max-width:100%;height:auto;border:1px solid var(--line);border-radius:10px;margin-top:8px"></canvas>
        <noscript><embed src="<?= e($pdfVer['url']) . '#page=' . (int)$pdfVer['pagina'] ?>" type="application/pdf" style="width:100%;height:600px;border:1px solid var(--line);border-radius:10px;margin-top:8px"></noscript>
      </div>
    </details>
    <script type="module" src="<?= e(url('assets/js/pdfprint.js')) ?>"></script>
    <script nomodule>(function(){var b=document.querySelector('.pdfprint');if(!b)return;var s=b.querySelector('.pdfprint-status');s.textContent='';var a=document.createElement('a');a.href=b.getAttribute('data-pdf')+'#page='+(b.getAttribute('data-page')||'1');a.target='_blank';a.textContent='Abrir o PDF na página '+(b.getAttribute('data-page')||'1')+' ↗';s.appendChild(a);})();</script>
  <?php endif; ?>
  <div id="feedback"></div>

  <div class="alts">
    <?php foreach ($alts as $i => $a): ?>
      <div class="alt" data-alt="<?= $i ?>"><span class="alt-letter"><?= LETRAS[$i] ?></span><span><?= e($a) ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if ($uid): ?>
    <button class="btn btn-gold" id="btnResponder" disabled>Responder</button>
    <button class="btn" id="btnVerResolucao" type="button" onclick="document.getElementById('resolucaoBox').style.display='';this.style.display='none'">👁️ Ver resolução</button>
  <?php else: ?>
    <p><a class="btn btn-gold" href="<?= e(url('login.php?next=' . urlencode($_SERVER['REQUEST_URI']))) ?>">Entre para responder e ver a resolução →</a></p>
  <?php endif; ?>

  <div class="resolucao" id="resolucaoBox" style="display:none">
    <b>📚 Resolução comentada</b>
    <div class="res-body"><p><?= $q['resolucao'] ?></p></div>
    <?php if ($uid): ?>
      <div class="q-actions">
        <button class="btn btn-small" id="btnIaQuestao">🤖 Explicar com IA</button>
      </div>
      <div class="resolucao" id="iaQuestaoBox" style="display:none;margin-top:10px"></div>
    <?php endif; ?>
  </div>

  <?php if ($uid): ?>
  <div class="q-actions">
    <button class="btn btn-small fav-btn <?= $fav ? 'faved' : '' ?>" data-fav="<?= (int)$q['id'] ?>"><?= $fav ? '⭐ Favoritada' : '☆ Favoritar' ?></button>
    <a class="btn btn-small" href="<?= e(url('caderno.php')) ?>">📓 Meu caderno</a>
    <span id="nextBox"><a class="btn btn-small btn-gold" href="<?= $nextId ? e(url('resolver.php?id=' . $nextId)) : e(url('questoes.php')) ?>">Próxima questão →</a></span>
  </div>
  <?php endif; ?>
</div>

<div class="card comments">
  <h3>💬 Comentários e resoluções da galera (<?= count($comments) ?>)</h3>
  <?php if ($uid): ?>
    <form id="commentForm" data-qid="<?= (int)$q['id'] ?>" class="form">
      <label style="margin-bottom:6px">Publique sua resolução ou dúvida
        <textarea id="commentText" style="min-height:70px" placeholder="Ex.: Resolvi por Pitágoras..."></textarea>
      </label>
      <button class="btn btn-small btn-gold" type="submit">Publicar</button>
    </form>
  <?php else: ?>
    <p class="muted"><a href="<?= e(url('login.php')) ?>">Entre</a> para comentar.</p>
  <?php endif; ?>
  <div id="commentList" style="margin-top:12px">
    <?php if (!$comments): ?><p class="muted" id="noComments">Seja o primeiro a comentar! 🎖️</p><?php endif; ?>
    <?php foreach ($comments as $c): ?>
      <div class="comment"><span class="who"><?= e($c['nome']) ?></span><span class="when"><?= e(fmt_data($c['created_at'])) ?></span><div><?= nl2br(e($c['texto'])) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
