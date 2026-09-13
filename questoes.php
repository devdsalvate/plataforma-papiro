<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$uid = $user ? (int)$user['id'] : 0;
$pdo = db();

// ---------- filtros ----------
$fConcurso = (string)($_GET['concurso'] ?? '');
$fAno = (string)($_GET['ano'] ?? '');
$fMateria = (string)($_GET['materia'] ?? '');
$fDif = (string)($_GET['dificuldade'] ?? '');
$fStatus = (string)($_GET['status'] ?? 'todas');
$fBusca = trim((string)($_GET['busca'] ?? ''));
if (!in_array($fConcurso, CONCURSOS_TODOS, true)) $fConcurso = '';
if (!in_array($fMateria, MATERIAS, true)) $fMateria = '';
if (!in_array($fDif, DIFICULDADES, true)) $fDif = '';
if (!in_array($fStatus, ['todas', 'nao_resolvidas', 'erradas', 'favoritas'], true)) $fStatus = 'todas';
if (!$uid && $fStatus !== 'todas') $fStatus = 'todas';

$where = ['q.ativo = 1'];
$params = [];
if ($fConcurso !== '') { $where[] = 'q.concurso = ?'; $params[] = $fConcurso; }
if ($fAno !== '' && ctype_digit($fAno)) { $where[] = 'q.ano = ?'; $params[] = (int)$fAno; }
if ($fMateria !== '') { $where[] = 'q.materia = ?'; $params[] = $fMateria; }
if ($fDif !== '') { $where[] = 'q.dificuldade = ?'; $params[] = $fDif; }
if ($fBusca !== '') { $where[] = '(q.enunciado LIKE ? OR q.assunto LIKE ?)'; $params[] = "%$fBusca%"; $params[] = "%$fBusca%"; }
if ($fStatus === 'nao_resolvidas') { $where[] = 'NOT EXISTS (SELECT 1 FROM respostas r WHERE r.user_id = ? AND r.questao_id = q.id)'; $params[] = $uid; }
if ($fStatus === 'erradas') { $where[] = 'EXISTS (SELECT 1 FROM caderno_erros c WHERE c.user_id = ? AND c.questao_id = q.id)'; $params[] = $uid; }
if ($fStatus === 'favoritas') { $where[] = 'EXISTS (SELECT 1 FROM favoritos f WHERE f.user_id = ? AND f.questao_id = q.id)'; $params[] = $uid; }

$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) FROM questoes q WHERE $w");
$st->execute($params);
$total = (int)$st->fetchColumn();

$porPagina = 10;
$paginas = max(1, (int)ceil($total / $porPagina));
$pag = min($paginas, max(1, (int)($_GET['pag'] ?? 1)));
$offset = ($pag - 1) * $porPagina;

$st = $pdo->prepare("SELECT q.* FROM questoes q WHERE $w ORDER BY q.concurso, q.ano DESC, q.id LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$lista = $st->fetchAll();

$anos = $pdo->query('SELECT DISTINCT ano FROM questoes WHERE ativo = 1 ORDER BY ano DESC')->fetchAll(PDO::FETCH_COLUMN);
$respostas = $uid ? user_answers_map($uid, array_column($lista, 'id')) : [];

function qlink(array $extra): string {
    $q = array_merge($_GET, $extra, ['pag' => 1]);
    return 'questoes.php?' . http_build_query($q);
}
$baseQ = $_GET;
unset($baseQ['pag']);
$qsBase = http_build_query($baseQ);

$title = 'Questões';
$active = 'questoes';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>📝 Questões</h1>
  <span class="badge b-navy"><?= $total ?> encontrada(s)</span>
</div>

<div class="tabs">
  <a class="tab <?= $fConcurso === '' ? 'active' : '' ?>" href="<?= e(qlink(['concurso' => ''])) ?>">Todas</a>
  <?php foreach (CONCURSOS_FOCO as $c): ?>
    <a class="tab <?= $fConcurso === $c ? 'active' : '' ?>" href="<?= e(qlink(['concurso' => $c])) ?>">★ <?= concurso_icone($c) ?> <?= e(concurso_nome($c)) ?></a>
  <?php endforeach; ?>
</div>
<div class="tabs">
  <?php foreach (['ESA', 'EsPCEx', 'AFA', 'IME', 'EEAM', 'CFN', 'Outro'] as $c): ?>
    <a class="tab <?= $fConcurso === $c ? 'active' : '' ?>" href="<?= e(qlink(['concurso' => $c])) ?>"><?= concurso_icone($c) ?> <?= e(concurso_nome($c)) ?></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <input type="hidden" name="concurso" value="<?= e($fConcurso) ?>">
  <label>Ano
    <select name="ano"><option value="">Todos</option>
      <?php foreach ($anos as $a): ?><option value="<?= $a ?>" <?= $fAno === (string)$a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Matéria
    <select name="materia"><option value="">Todas</option>
      <?php foreach (MATERIAS as $m): ?><option <?= $fMateria === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Dificuldade
    <select name="dificuldade"><option value="">Todas</option>
      <?php foreach (DIFICULDADES as $d): ?><option <?= $fDif === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php if ($uid): ?>
  <label>Status
    <select name="status">
      <option value="todas" <?= $fStatus === 'todas' ? 'selected' : '' ?>>Todas</option>
      <option value="nao_resolvidas" <?= $fStatus === 'nao_resolvidas' ? 'selected' : '' ?>>Não resolvidas</option>
      <option value="erradas" <?= $fStatus === 'erradas' ? 'selected' : '' ?>>Meus erros</option>
      <option value="favoritas" <?= $fStatus === 'favoritas' ? 'selected' : '' ?>>Favoritas</option>
    </select>
  </label>
  <?php endif; ?>
  <label>Buscar <input name="busca" value="<?= e($fBusca) ?>" placeholder="assunto ou palavra..."></label>
  <button class="btn btn-gold" type="submit">🔍 Filtrar</button>
</form>

<?php if (!$lista): ?>
  <div class="card"><p>😕 Nenhuma questão encontrada com esses filtros. <a href="<?= e(url('questoes.php')) ?>">Limpar filtros</a></p></div>
<?php endif; ?>

<?php foreach ($lista as $q):
    $r = $respostas[(int)$q['id']] ?? null;
    $snippet = mb_substr(trim(strip_tags($q['enunciado'])), 0, 150) . '…';
?>
<a class="q-item" href="<?= e(url('resolver.php?id=' . $q['id'])) ?>">
  <div class="q-badges">
    <span class="badge b-navy"><?= concurso_icone($q['concurso']) ?> <?= e(concurso_nome($q['concurso'])) ?></span>
    <span class="badge"><?= (int)$q['ano'] ?></span>
    <span class="badge b-gold"><?= e($q['materia']) ?></span>
    <span class="badge"><?= e($q['dificuldade']) ?></span>
    <?php if ((int)($q['pagina'] ?? 0) > 0): ?>
      <span class="badge">📄 p. <?= ($q['regiao'] ?? '') === '~' ? '~' : '' ?><?= (int)$q['pagina'] ?></span>
    <?php endif; ?>
    <?php if ($r): ?>
      <span class="q-status"><?= $r['correta'] ? '<span class="badge b-green">✅ acertou</span>' : '<span class="badge b-red">❌ errou</span>' ?></span>
    <?php endif; ?>
  </div>
  <div class="q-snippet"><?= e($snippet) ?></div>
  <div class="muted"><?= e($q['assunto']) ?></div>
</a>
<?php endforeach; ?>

<?php if ($paginas > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $paginas; $i++): ?>
    <a class="tab <?= $i === $pag ? 'active' : '' ?>" href="<?= e(url('questoes.php?' . ($qsBase !== '' ? $qsBase . '&' : '') . 'pag=' . $i)) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
