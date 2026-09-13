<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$pdo = db();

$fConcurso = (string)($_GET['concurso'] ?? '');
if (!in_array($fConcurso, CONCURSOS_TODOS, true)) $fConcurso = '';
$fRev = (string)($_GET['rev'] ?? '');
if (!in_array($fRev, ['', '0', '1'], true)) $fRev = '';

$where = 'c.user_id = ?';
$params = [$uid];
if ($fConcurso !== '') { $where .= ' AND q.concurso = ?'; $params[] = $fConcurso; }
if ($fRev !== '') { $where .= ' AND c.revisada = ?'; $params[] = (int)$fRev; }

$st = $pdo->prepare("SELECT c.*, q.concurso, q.ano, q.materia, q.assunto, q.dificuldade, q.enunciado FROM caderno_erros c JOIN questoes q ON q.id = c.questao_id WHERE $where ORDER BY c.revisada, c.created_at DESC");
$st->execute($params);
$erros = $st->fetchAll();

$title = 'Caderno de erros';
$active = 'caderno';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>📓 Caderno de erros</h1>
  <span class="badge b-red"><?= count($erros) ?> erro(s)</span>
</div>
<p class="muted">Toda questão que você erra cai aqui automaticamente. Anote <b>por que errou</b>, revise e marque como revisada. 🔁</p>

<form class="filters" method="get">
  <label>Concurso
    <select name="concurso"><option value="">Todos</option>
      <?php foreach (CONCURSOS_TODOS as $c): ?><option value="<?= e($c) ?>" <?= $fConcurso === $c ? 'selected' : '' ?>><?= e(concurso_nome($c)) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Revisão
    <select name="rev">
      <option value="" <?= $fRev === '' ? 'selected' : '' ?>>Todas</option>
      <option value="0" <?= $fRev === '0' ? 'selected' : '' ?>>A revisar</option>
      <option value="1" <?= $fRev === '1' ? 'selected' : '' ?>>Revisadas</option>
    </select>
  </label>
  <button class="btn btn-gold" type="submit">🔍 Filtrar</button>
</form>

<?php if (!$erros): ?>
  <div class="card"><p>🎉 Nenhum erro por aqui! Continue resolvendo <a href="<?= e(url('questoes.php')) ?>">questões</a> — e se errar, reviva o erro aqui.</p></div>
<?php endif; ?>

<?php foreach ($erros as $r):
    $snippet = mb_substr(trim(strip_tags($r['enunciado'])), 0, 140) . '…'; ?>
<div class="card" data-err-row style="<?= $r['revisada'] ? 'opacity:.65' : '' ?>">
  <div class="q-badges">
    <span class="badge b-navy"><?= concurso_icone($r['concurso']) ?> <?= e(concurso_nome($r['concurso'])) ?> <?= (int)$r['ano'] ?></span>
    <span class="badge b-gold"><?= e($r['materia']) ?></span>
    <span class="badge"><?= e($r['dificuldade']) ?></span>
    <span class="badge b-red"><?= e($r['motivo']) ?></span>
    <span class="q-status"><label><input type="checkbox" data-revisada="<?= (int)$r['questao_id'] ?>" <?= $r['revisada'] ? 'checked' : '' ?>> revisada</label></span>
  </div>
  <p><a href="<?= e(url('resolver.php?id=' . $r['questao_id'])) ?>"><?= e($snippet) ?></a></p>
  <label class="muted">✏️ Por que errei? (sua anotação)
    <textarea class="note" data-note="<?= (int)$r['questao_id'] ?>" placeholder="Ex.: confundi seno com cosseno no 2º quadrante..."><?= e($r['anotacao'] ?? '') ?></textarea>
  </label>
  <div class="q-actions">
    <button class="btn btn-small btn-gold" data-save-note="<?= (int)$r['questao_id'] ?>">💾 Salvar anotação</button>
    <a class="btn btn-small" href="<?= e(url('resolver.php?id=' . $r['questao_id'])) ?>">🔁 Tentar de novo</a>
    <button class="btn btn-small btn-danger" data-remove-err="<?= (int)$r['questao_id'] ?>">🗑️ Remover</button>
  </div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
