<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$uid = $user ? (int)$user['id'] : 0;
$pdo = db();

// Em instalações atualizadas por cima de uma versão antiga, garante que o acervo
// oficial já esteja disponível assim que a página de Questões for aberta.
try {
    $officialCount = (int)$pdo->query("SELECT COUNT(*) FROM questoes WHERE slug LIKE 'oficial-%'")->fetchColumn();
    if ($officialCount < 3049) {
        require_once __DIR__ . '/includes/official_bank.php';
        official_bank_sync($pdo);
    }
    require_once __DIR__ . '/includes/default_admins.php';
    ensure_default_admins($pdo);
    remove_password_recovery_storage($pdo);
} catch (Throwable $e) {
    // A página continua funcionando com o banco existente; o admin pode sincronizar manualmente.
}


$fConcurso = (string)($_GET['concurso'] ?? '');
$fAno = (string)($_GET['ano'] ?? '');
$fMateria = (string)($_GET['materia'] ?? '');
$fDif = (string)($_GET['dificuldade'] ?? '');
$fStatus = (string)($_GET['status'] ?? 'todas');
$fBusca = trim((string)($_GET['busca'] ?? ''));
$fOrdem = (string)($_GET['ordem'] ?? 'recentes');
if (!in_array($fConcurso, CONCURSOS_TODOS, true)) $fConcurso = '';
if (!in_array($fMateria, MATERIAS, true)) $fMateria = '';
if (!in_array($fDif, DIFICULDADES, true)) $fDif = '';
if (!in_array($fStatus, ['todas', 'nao_resolvidas', 'erradas', 'favoritas'], true)) $fStatus = 'todas';
if (!in_array($fOrdem, ['recentes', 'antigas', 'materia'], true)) $fOrdem = 'recentes';
if (!$uid && $fStatus !== 'todas') $fStatus = 'todas';

$where = ['q.ativo = 1']; $params = [];
if ($fConcurso !== '') { $where[] = 'q.concurso = ?'; $params[] = $fConcurso; }
if ($fAno !== '' && ctype_digit($fAno)) { $where[] = 'q.ano = ?'; $params[] = (int)$fAno; }
if ($fMateria !== '') { $where[] = 'q.materia = ?'; $params[] = $fMateria; }
if ($fDif !== '') { $where[] = 'q.dificuldade = ?'; $params[] = $fDif; }
if ($fBusca !== '') {
    $where[] = '(q.enunciado LIKE ? OR q.assunto LIKE ? OR q.concurso LIKE ?)';
    $params[] = "%$fBusca%"; $params[] = "%$fBusca%"; $params[] = "%$fBusca%";
}
if ($fStatus === 'nao_resolvidas') { $where[] = 'NOT EXISTS (SELECT 1 FROM respostas r WHERE r.user_id = ? AND r.questao_id = q.id)'; $params[] = $uid; }
if ($fStatus === 'erradas') { $where[] = 'EXISTS (SELECT 1 FROM caderno_erros c WHERE c.user_id = ? AND c.questao_id = q.id)'; $params[] = $uid; }
if ($fStatus === 'favoritas') { $where[] = 'EXISTS (SELECT 1 FROM favoritos f WHERE f.user_id = ? AND f.questao_id = q.id)'; $params[] = $uid; }
$w = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM questoes q WHERE $w"); $st->execute($params); $total = (int)$st->fetchColumn();
$porPagina = 10; $paginas = max(1, (int)ceil($total / $porPagina)); $pag = min($paginas, max(1, (int)($_GET['pag'] ?? 1))); $offset = ($pag - 1) * $porPagina;
$orderSql = $fOrdem === 'antigas' ? 'q.ano ASC, q.id ASC' : ($fOrdem === 'materia' ? 'q.materia ASC, q.assunto ASC, q.id DESC' : 'q.ano DESC, q.id DESC');
$st = $pdo->prepare("SELECT q.* FROM questoes q WHERE $w ORDER BY $orderSql LIMIT $porPagina OFFSET $offset"); $st->execute($params); $lista = $st->fetchAll();
$anos = $pdo->query('SELECT DISTINCT ano FROM questoes WHERE ativo = 1 AND ano > 0 ORDER BY ano DESC')->fetchAll(PDO::FETCH_COLUMN);
$respostas = $uid ? user_answers_map($uid, array_column($lista, 'id')) : [];

function qurl(array $extra = []): string { $q = array_merge($_GET, $extra); foreach ($q as $k=>$v) if ($v === '' || $v === null) unset($q[$k]); return url('questoes.php' . ($q ? '?' . http_build_query($q) : '')); }
function preview_data(array $q): ?array {
    if (empty($q['origem']) || (int)($q['pagina'] ?? 0) <= 0) return null;
    $base = basename((string)$q['origem']);
    if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'pdf' || !is_file(APP_ROOT . '/simulados/' . $base)) return null;
    if (!preg_match('/^(\d*\.?\d+)-(\d*\.?\d+)$/', (string)($q['regiao'] ?? ''), $m)) return null;
    $y0=(float)$m[1]; $y1=(float)$m[2]; if (!($y1>$y0)) return null;
    return ['url'=>url('simulados/' . rawurlencode($base)),'page'=>(int)$q['pagina'],'y0'=>$y0,'y1'=>$y1];
}

$title = 'Questões'; $active = 'questoes';
$extra_js = '<script type="module" src="' . e(url('assets/js/question-previews.js')) . '"></script>';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Banco de questões</h1><div class="muted">Filtre, visualize o enunciado original e abra somente o que quiser resolver.</div></div>
  <span class="spacer"></span>
  <a class="btn btn-primary" href="<?= e(url('simulados.php')) ?>">Gerar simulado</a>
</div>

<div class="question-layout">
  <aside class="question-filter-panel">
    <h3>Filtrar questões</h3>
    <form method="get">
      <div><label for="busca">Busca</label><input id="busca" name="busca" value="<?= e($fBusca) ?>" placeholder="palavra, assunto..."></div>
      <div><label for="concurso">Concurso</label><select id="concurso" name="concurso"><option value="">Todos</option><?php foreach(CONCURSOS_TODOS as $c): if($c==='Outro') continue; ?><option value="<?= e($c) ?>" <?= $fConcurso===$c?'selected':'' ?>><?= e(concurso_nome($c)) ?></option><?php endforeach; ?></select></div>
      <div><label for="materia">Disciplina</label><select id="materia" name="materia"><option value="">Todas</option><?php foreach(MATERIAS as $m): if($m==='Geral') continue; ?><option value="<?= e($m) ?>" <?= $fMateria===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
      <div><label for="ano">Ano</label><select id="ano" name="ano"><option value="">Todos</option><?php foreach($anos as $a): ?><option value="<?= (int)$a ?>" <?= $fAno===(string)$a?'selected':'' ?>><?= (int)$a ?></option><?php endforeach; ?></select></div>
      <div><label for="dificuldade">Dificuldade</label><select id="dificuldade" name="dificuldade"><option value="">Todas</option><?php foreach(DIFICULDADES as $d): ?><option value="<?= e($d) ?>" <?= $fDif===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
      <?php if($uid): ?><div><label for="status">Status</label><select id="status" name="status"><option value="todas" <?= $fStatus==='todas'?'selected':'' ?>>Todas</option><option value="nao_resolvidas" <?= $fStatus==='nao_resolvidas'?'selected':'' ?>>Não resolvidas</option><option value="erradas" <?= $fStatus==='erradas'?'selected':'' ?>>Meus erros</option><option value="favoritas" <?= $fStatus==='favoritas'?'selected':'' ?>>Favoritas</option></select></div><?php endif; ?>
      <div class="filter-actions"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn" href="<?= e(url('questoes.php')) ?>">Limpar</a></div>
    </form>
  </aside>

  <section class="question-results">
    <div class="question-results-toolbar">
      <div class="question-count"><b><?= number_format($total,0,',','.') ?></b> questão(ões) encontrada(s)</div><span class="grow"></span>
      <form method="get"><?php foreach($_GET as $k=>$v): if(in_array($k,['ordem','pag'],true)) continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php endforeach; ?><select name="ordem" onchange="this.form.submit()"><option value="recentes" <?= $fOrdem==='recentes'?'selected':'' ?>>Mais recentes</option><option value="antigas" <?= $fOrdem==='antigas'?'selected':'' ?>>Mais antigas</option><option value="materia" <?= $fOrdem==='materia'?'selected':'' ?>>Por disciplina</option></select></form>
    </div>

    <?php if(!$lista): ?><div class="card"><b>Nenhuma questão encontrada.</b><p class="muted">Tente remover um filtro ou fazer uma busca mais ampla.</p></div><?php endif; ?>

    <?php foreach($lista as $q):
      $r=$respostas[(int)$q['id']]??null; $pv=preview_data($q);
      $snippet=trim(preg_replace('/\s+/u',' ',strip_tags((string)$q['enunciado']))??'');
      if($snippet==='') $snippet='Questão oficial — consulte a prévia do PDF original.';
      if(mb_strlen($snippet)>460) $snippet=mb_substr($snippet,0,460).'…';
    ?>
    <article class="question-card">
      <div class="question-card-main">
        <div class="question-meta"><span class="question-id">Q<?= (int)$q['id'] ?></span><span class="badge b-navy"><?= e(concurso_nome((string)$q['concurso'])) ?></span><?php if((int)$q['ano']>0): ?><span class="badge"><?= (int)$q['ano'] ?></span><?php endif; ?><span class="badge b-blue"><?= e($q['materia']) ?></span><?php if($r): ?><span class="badge <?= $r['correta']?'b-green':'b-red' ?>"><?= $r['correta']?'Resolvida':'Revisar' ?></span><?php endif; ?></div>
        <p class="question-title"><?= e($snippet) ?></p>
        <div class="question-topic"><?= e((string)$q['assunto']) ?><?= (int)($q['pagina']??0)>0 ? ' · PDF p. '.(int)$q['pagina'] : '' ?></div>
        <div class="question-actions"><a class="btn btn-primary" href="<?= e(url('resolver.php?id='.(int)$q['id'])) ?>">Resolver questão</a><?php if($uid): ?><button type="button" class="btn fav-btn <?= !empty($r['fav'])?'faved':'' ?>" data-fav="<?= (int)$q['id'] ?>">Favoritar</button><?php endif; ?></div>
      </div>
      <?php if($pv): ?>
        <figure class="question-preview" data-question-preview data-pdf="<?= e($pv['url']) ?>" data-page="<?= (int)$pv['page'] ?>" data-y0="<?= e((string)$pv['y0']) ?>" data-y1="<?= e((string)$pv['y1']) ?>">
          <div class="question-preview-head"><b>Print da questão original</b><a href="<?= e($pv['url'] . '#page=' . (int)$pv['page']) ?>" target="_blank" rel="noopener">Abrir PDF</a></div>
          <div class="question-preview-placeholder">Carregando print da questão...</div>
          <canvas hidden aria-label="Prévia visual da questão"></canvas>
          <figcaption>PDF original · página <?= (int)$pv['page'] ?></figcaption>
        </figure>
      <?php else: ?><div class="question-preview"><div class="question-preview-placeholder">Questão cadastrada em texto<br><span class="question-source-note">sem recorte de PDF</span></div></div><?php endif; ?>
    </article>
    <?php endforeach; ?>

    <?php if($paginas>1): $ini=max(1,$pag-3);$fim=min($paginas,$pag+3); ?>
      <div class="pager"><?php if($pag>1): ?><a class="tab" href="<?= e(qurl(['pag'=>$pag-1])) ?>">Anterior</a><?php endif; ?><?php for($i=$ini;$i<=$fim;$i++): ?><a class="tab <?= $i===$pag?'active':'' ?>" href="<?= e(qurl(['pag'=>$i])) ?>"><?= $i ?></a><?php endfor; ?><?php if($pag<$paginas): ?><a class="tab" href="<?= e(qurl(['pag'=>$pag+1])) ?>">Próxima</a><?php endif; ?></div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
