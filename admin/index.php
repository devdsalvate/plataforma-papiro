<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/import_lib.php';
$user = require_admin();
$pdo = db();
import_migrate(); // garante tabela importacoes + coluna questoes.origem
$tab = (string)($_GET['tab'] ?? 'dash');
if (!in_array($tab, ['dash', 'questoes', 'importar', 'usuarios', 'comentarios', 'videos', 'trilhas', 'stats'], true)) $tab = 'dash';

/* ================= POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $form = (string)($_POST['form'] ?? '');

    if ($form === 'q_save') {
        $id = (int)($_POST['id'] ?? 0);
        $concurso = in_array($_POST['concurso'] ?? '', CONCURSOS_TODOS, true) ? $_POST['concurso'] : 'Outro';
        $ano = max(1990, min(2100, (int)($_POST['ano'] ?? date('Y'))));
        $materia = in_array($_POST['materia'] ?? '', MATERIAS, true) ? $_POST['materia'] : 'Geral';
        $dif = in_array($_POST['dificuldade'] ?? '', DIFICULDADES, true) ? $_POST['dificuldade'] : 'Médio';
        $assunto = trim((string)($_POST['assunto'] ?? ''));
        $enunciado = trim((string)($_POST['enunciado'] ?? ''));
        $alts = [trim((string)($_POST['alt_a'] ?? '')), trim((string)($_POST['alt_b'] ?? '')), trim((string)($_POST['alt_c'] ?? '')), trim((string)($_POST['alt_d'] ?? '')), trim((string)($_POST['alt_e'] ?? ''))];
        $gab = min(4, max(0, (int)($_POST['gabarito'] ?? 0)));
        $resolucao = trim((string)($_POST['resolucao'] ?? ''));
        if ($enunciado === '' || $alts[0] === '' || $resolucao === '') {
            flash('error', 'Preencha enunciado, alternativas e resolução.');
        } else {
            if ($id > 0) {
                $pdo->prepare('UPDATE questoes SET concurso=?,ano=?,materia=?,assunto=?,dificuldade=?,enunciado=?,alt_a=?,alt_b=?,alt_c=?,alt_d=?,alt_e=?,gabarito=?,resolucao=? WHERE id=?')
                    ->execute([$concurso, $ano, $materia, $assunto, $dif, $enunciado, ...$alts, $gab, $resolucao, $id]);
                flash('success', "Questão #$id atualizada! ✅");
            } else {
                do {
                    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', "$concurso $ano $assunto"), '-')) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                    $st = $pdo->prepare('SELECT id FROM questoes WHERE slug = ?');
                    $st->execute([$slug]);
                } while ($st->fetch());
                $pdo->prepare('INSERT INTO questoes (slug,concurso,ano,materia,assunto,dificuldade,enunciado,alt_a,alt_b,alt_c,alt_d,alt_e,gabarito,resolucao,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$slug, $concurso, $ano, $materia, $assunto, $dif, $enunciado, ...$alts, $gab, $resolucao, $user['id']]);
                flash('success', 'Questão criada! ✅');
            }
            redirect('admin/index.php?tab=questoes');
        }
    } elseif ($form === 'q_del') {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT id FROM questao_imagens WHERE questao_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ii) questao_excluir_imagem((int)$ii);
        foreach (['respostas' => 'questao_id', 'favoritos' => 'questao_id', 'caderno_erros' => 'questao_id', 'comentarios' => 'questao_id'] as $tb => $col) {
            $pdo->prepare("DELETE FROM $tb WHERE $col = ?")->execute([$id]);
        }
        $pdo->prepare('DELETE FROM questoes WHERE id = ?')->execute([$id]);
        flash('info', "Questão #$id excluída.");
        redirect('admin/index.php?tab=questoes');
    } elseif ($form === 'q_img_upload') {
        $qid = (int)($_POST['qid'] ?? 0);
        if ($qid > 0 && !empty($_FILES['imagens'])) {
            [$upOk, $upErr] = questao_upload_imagens($qid, $_FILES['imagens']);
            if ($upOk) flash('success', count($upOk) . ' imagem(ns) enviada(s)! ✅');
            foreach ($upErr as $ue) flash('error', $ue);
        }
        redirect('admin/index.php?tab=questoes&edit=' . $qid);
    } elseif ($form === 'q_img_del') {
        questao_excluir_imagem((int)($_POST['img_id'] ?? 0));
        flash('info', 'Imagem excluída.');
        redirect('admin/index.php?tab=questoes&edit=' . (int)($_POST['qid'] ?? 0));
    } elseif ($form === 'q_toggle') {
        $pdo->prepare('UPDATE questoes SET ativo = 1 - ativo WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('admin/index.php?tab=questoes');
    } elseif ($form === 'u_role') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$user['id']) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([($_POST['role'] ?? '') === 'admin' ? 'admin' : 'aluno', $id]);
            flash('success', 'Perfil atualizado.');
        }
        redirect('admin/index.php?tab=usuarios');
    } elseif ($form === 'u_del') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$user['id']) {
            foreach (['respostas' => 'user_id', 'favoritos' => 'user_id', 'caderno_erros' => 'user_id', 'comentarios' => 'user_id', 'study_sessions' => 'user_id', 'trilha_progresso' => 'user_id', 'grupo_membros' => 'user_id', 'ia_perguntas' => 'user_id'] as $tb => $col) {
                $pdo->prepare("DELETE FROM $tb WHERE $col = ?")->execute([$id]);
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('info', 'Usuário excluído.');
        }
        redirect('admin/index.php?tab=usuarios');
    } elseif ($form === 'c_toggle') {
        $pdo->prepare('UPDATE comentarios SET aprovado = 1 - aprovado WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('admin/index.php?tab=comentarios');
    } elseif ($form === 'c_del') {
        $pdo->prepare('DELETE FROM comentarios WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('info', 'Comentário excluído.');
        redirect('admin/index.php?tab=comentarios');
    } elseif ($form === 'v_save') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $vurl = trim((string)($_POST['vurl'] ?? ''));
        $concurso = in_array($_POST['concurso'] ?? '', CONCURSOS_TODOS, true) ? $_POST['concurso'] : 'Outro';
        $materia = in_array($_POST['materia'] ?? '', MATERIAS, true) ? $_POST['materia'] : 'Geral';
        $desc = trim((string)($_POST['descricao'] ?? ''));
        if ($titulo === '') flash('error', 'Informe o título.');
        else {
            if ($id > 0) $pdo->prepare('UPDATE videoaulas SET titulo=?,url=?,concurso=?,materia=?,descricao=? WHERE id=?')->execute([$titulo, $vurl, $concurso, $materia, $desc, $id]);
            else $pdo->prepare('INSERT INTO videoaulas (titulo,url,concurso,materia,descricao) VALUES (?,?,?,?,?)')->execute([$titulo, $vurl, $concurso, $materia, $desc]);
            flash('success', 'Videoaula salva! ✅');
            redirect('admin/index.php?tab=videos');
        }
    } elseif ($form === 'v_del') {
        $pdo->prepare('DELETE FROM videoaulas WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('info', 'Videoaula excluída.');
        redirect('admin/index.php?tab=videos');
    } elseif ($form === 't_mod_add') {
        $tid = (int)$_POST['trilha_id'];
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        if ($tid > 0 && $titulo !== '') {
            $st = $pdo->prepare('SELECT COALESCE(MAX(ordem),0)+1 FROM trilha_modulos WHERE trilha_id = ?');
            $st->execute([$tid]);
            $pdo->prepare('INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (?,?,?)')->execute([$tid, $titulo, (int)$st->fetchColumn()]);
            flash('success', 'Módulo adicionado! ✅');
        }
        redirect('admin/index.php?tab=trilhas');
    } elseif ($form === 't_mod_del') {
        $mid = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM trilha_progresso WHERE modulo_id = ?')->execute([$mid]);
        $pdo->prepare('DELETE FROM trilha_modulos WHERE id = ?')->execute([$mid]);
        flash('info', 'Módulo excluído.');
        redirect('admin/index.php?tab=trilhas');
    } elseif ($form === 't_toggle') {
        $pdo->prepare('UPDATE trilhas SET ativo = 1 - ativo WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('admin/index.php?tab=trilhas');
    }
}

$title = 'Administração';
$active = 'admin';
include dirname(__DIR__) . '/includes/header.php';
$tabs = ['dash' => '📊 Painel', 'questoes' => '📝 Questões', 'importar' => '📥 Importar PDFs', 'usuarios' => '👥 Usuários', 'comentarios' => '💬 Comentários', 'videos' => '🎥 Videoaulas', 'trilhas' => '🗺️ Trilhas', 'stats' => '📈 Estatísticas'];
?>
<div class="page-head"><div><h1>Administração</h1></div><span class="spacer"></span><a class="btn btn-primary" href="<?= e(url('admin/sincronizar_banco.php')) ?>">Sincronizar acervo oficial</a></div>
<div class="admin-tabs tabs">
  <?php foreach ($tabs as $k => $l): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('admin/index.php?tab=' . $k)) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'dash'):
    $nUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $nQ = (int)$pdo->query('SELECT COUNT(*) FROM questoes WHERE ativo = 1')->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE created_at >= ?');
    $st->execute([date('Y-m-d 00:00:00')]);
    $nHoje = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COALESCE(SUM(duracao_seg),0) FROM study_sessions WHERE inicio >= ?');
    $st->execute([date('Y-m-d 00:00:00', strtotime('monday this week'))]);
    $segSem = (int)$st->fetchColumn();
    $recentUsers = $pdo->query('SELECT * FROM users ORDER BY id DESC LIMIT 5')->fetchAll();
    $recentResp = $pdo->query('SELECT r.*, u.nome, q.concurso, q.ano FROM respostas r JOIN users u ON u.id = r.user_id JOIN questoes q ON q.id = r.questao_id ORDER BY r.id DESC LIMIT 8')->fetchAll();
?>
<div class="grid-stats">
  <div class="stat"><div class="stat-num"><?= $nUsers ?></div><div class="stat-label">👥 usuários</div></div>
  <div class="stat"><div class="stat-num"><?= $nQ ?></div><div class="stat-label">📝 questões ativas</div></div>
  <div class="stat"><div class="stat-num"><?= $nHoje ?></div><div class="stat-label">✅ respostas hoje</div></div>
  <div class="stat"><div class="stat-num"><?= e(fmt_duracao($segSem)) ?></div><div class="stat-label">⏱️ horas na semana</div></div>
</div>
<div class="grid-2">
  <div class="card"><h3>🆕 Últimos usuários</h3>
    <div class="table-wrap"><table class="table"><tr><th>Nome</th><th>Foco</th><th>Desde</th></tr>
    <?php foreach ($recentUsers as $u): ?><tr><td><?= e($u['nome']) ?></td><td><?= e($u['foco']) ?></td><td><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td></tr><?php endforeach; ?>
    </table></div>
  </div>
  <div class="card"><h3>⚡ Últimas respostas</h3>
    <div class="table-wrap"><table class="table"><tr><th>Aluno</th><th>Questão</th><th>Result.</th></tr>
    <?php foreach ($recentResp as $r): ?><tr><td><?= e($r['nome']) ?></td><td><a href="<?= e(url('resolver.php?id=' . $r['questao_id'])) ?>"><?= e($r['concurso']) ?> <?= (int)$r['ano'] ?></a></td><td><?= $r['correta'] ? '✅' : '❌' ?></td></tr><?php endforeach; ?>
    </table></div>
  </div>
</div>

<?php elseif ($tab === 'questoes'):
    $busca = trim((string)($_GET['busca'] ?? ''));
    $editId = (int)($_GET['edit'] ?? 0);
    $novo = isset($_GET['novo']);
    $editQ = null;
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM questoes WHERE id = ?');
        $st->execute([$editId]);
        $editQ = $st->fetch() ?: null;
    }
    if ($novo || $editQ):
        $f = $editQ ?: ['id' => 0, 'concurso' => 'EFOMM', 'ano' => date('Y'), 'materia' => 'Matemática', 'assunto' => '', 'dificuldade' => 'Médio', 'enunciado' => '', 'alt_a' => '', 'alt_b' => '', 'alt_c' => '', 'alt_d' => '', 'alt_e' => '', 'gabarito' => 0, 'resolucao' => ''];
?>
<div class="card">
  <h3><?= $editQ ? "✏️ Editar questão #{$editQ['id']}" : '➕ Nova questão' ?></h3>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="q_save">
    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
    <div class="grid-3">
      <label>Concurso<select name="concurso"><?php foreach (CONCURSOS_TODOS as $c): ?><option <?= $f['concurso'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label>Ano<input type="number" name="ano" value="<?= (int)$f['ano'] ?>"></label>
      <label>Dificuldade<select name="dificuldade"><?php foreach (DIFICULDADES as $d): ?><option <?= $f['dificuldade'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="grid-2">
      <label>Matéria<select name="materia"><?php foreach (MATERIAS as $m): ?><option <?= $f['materia'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></label>
      <label>Assunto<input name="assunto" value="<?= e($f['assunto']) ?>"></label>
    </div>
    <label>Enunciado (aceita HTML)<textarea name="enunciado"><?= e($f['enunciado']) ?></textarea></label>
    <div class="grid-2">
      <label>A)<input name="alt_a" value="<?= e($f['alt_a']) ?>"></label>
      <label>B)<input name="alt_b" value="<?= e($f['alt_b']) ?>"></label>
      <label>C)<input name="alt_c" value="<?= e($f['alt_c']) ?>"></label>
      <label>D)<input name="alt_d" value="<?= e($f['alt_d']) ?>"></label>
      <label>E)<input name="alt_e" value="<?= e($f['alt_e']) ?>"></label>
      <label>Gabarito<select name="gabarito"><?php foreach (LETRAS as $i => $L): ?><option value="<?= $i ?>" <?= (int)$f['gabarito'] === $i ? 'selected' : '' ?>>Letra <?= $L ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Resolução comentada (aceita HTML)<textarea name="resolucao"><?= e($f['resolucao']) ?></textarea></label>
    <button class="btn btn-gold" type="submit">💾 Salvar</button>
    <a class="btn" href="<?= e(url('admin/index.php?tab=questoes')) ?>">Cancelar</a>
  </form>
</div>
<?php if ($editQ):
    $stIm = $pdo->prepare('SELECT * FROM questao_imagens WHERE questao_id = ? ORDER BY id');
    $stIm->execute([(int)$editQ['id']]);
    $qimgs = $stIm->fetchAll();
?>
<div class="card">
  <h3>🖼️ Imagens da questão #<?= (int)$editQ['id'] ?> (<?= count($qimgs) ?>)</h3>
  <form method="post" enctype="multipart/form-data" class="form" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="q_img_upload">
    <input type="hidden" name="qid" value="<?= (int)$editQ['id'] ?>">
    <label style="margin:0">Adicionar (JPG/PNG/GIF/WebP, máx. 5 MB cada)<input type="file" name="imagens[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" required></label>
    <button class="btn btn-gold" type="submit">📤 Enviar</button>
  </form>
  <?php if ($qimgs): ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
    <?php foreach ($qimgs as $qi): ?>
    <div style="border:1px solid var(--line);border-radius:10px;padding:8px;text-align:center">
      <a href="<?= e(url('assets/uploads/questoes/' . $qi['arquivo'])) ?>" target="_blank"><img src="<?= e(url('assets/uploads/questoes/' . $qi['arquivo'])) ?>" style="max-width:160px;max-height:110px;border-radius:6px"></a><br>
      <span class="muted"><?= e($qi['legenda'] ?: $qi['arquivo']) ?></span><br>
      <form method="post" style="margin-top:4px" onsubmit="return confirm('Excluir imagem?')"><?= csrf_field() ?><input type="hidden" name="form" value="q_img_del"><input type="hidden" name="img_id" value="<?= (int)$qi['id'] ?>"><input type="hidden" name="qid" value="<?= (int)$editQ['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
    <?php else:
        if ($busca !== '') {
            $st = $pdo->prepare('SELECT * FROM questoes WHERE enunciado LIKE ? OR assunto LIKE ? ORDER BY id DESC LIMIT 100');
            $st->execute(["%$busca%", "%$busca%"]);
            $qs = $st->fetchAll();
        } else {
            $qs = $pdo->query('SELECT * FROM questoes ORDER BY id DESC LIMIT 100')->fetchAll();
        }
?>
<div class="card">
  <form class="filters" method="get" style="border:none;padding:0">
    <input type="hidden" name="tab" value="questoes">
    <label>Buscar <input name="busca" value="<?= e($busca) ?>"></label>
    <button class="btn" type="submit">🔍</button>
    <a class="btn btn-gold" href="<?= e(url('admin/index.php?tab=questoes&novo=1')) ?>">➕ Nova questão</a>
  </form>
  <div class="table-wrap"><table class="table">
    <tr><th>ID</th><th>Concurso</th><th>Ano</th><th>Matéria</th><th>Assunto</th><th>Dif.</th><th>Origem</th><th>Ativa</th><th>Ações</th></tr>
    <?php foreach ($qs as $q): ?>
    <tr>
      <td><?= (int)$q['id'] ?></td>
      <td><?= e($q['concurso']) ?></td><td><?= (int)$q['ano'] ?></td><td><?= e($q['materia']) ?></td>
      <td><?= e(mb_substr($q['assunto'], 0, 30)) ?></td><td><?= e($q['dificuldade']) ?></td>
      <td class="muted"><?= e(mb_substr($q['origem'] ?? '', 0, 18)) ?></td>
      <td><?= $q['ativo'] ? '✅' : '🚫' ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-small" href="<?= e(url('resolver.php?id=' . $q['id'])) ?>">👁️</a>
        <a class="btn btn-small" href="<?= e(url('admin/index.php?tab=questoes&edit=' . $q['id'])) ?>">✏️</a>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form" value="q_toggle"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn btn-small" title="Ativar/desativar">🔄</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir questão #<?= (int)$q['id'] ?> e seus dados?')"><?= csrf_field() ?><input type="hidden" name="form" value="q_del"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
    <?php endif; ?>

<?php elseif ($tab === 'usuarios'):
    $us = $pdo->query('SELECT *, (SELECT COUNT(*) FROM respostas r WHERE r.user_id = users.id) nresp FROM users ORDER BY id DESC')->fetchAll();
?>
<div class="card"><h3>👥 Usuários (<?= count($us) ?>)</h3>
  <div class="table-wrap"><table class="table">
    <tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Foco</th><th>Perfil</th><th>🔥</th><th>Resp.</th><th>Ações</th></tr>
    <?php foreach ($us as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td><td><?= e($u['nome']) ?></td><td><?= e($u['email']) ?></td>
      <td><?= e($u['foco']) ?></td><td><?= $u['role'] === 'admin' ? '🛠️ admin' : '🎖️ aluno' ?></td>
      <td><?= (int)$u['ofensiva'] ?></td><td><?= (int)$u['nresp'] ?></td>
      <td style="white-space:nowrap">
        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form" value="u_role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'aluno' : 'admin' ?>"><button class="btn btn-small"><?= $u['role'] === 'admin' ? '⬇️ rebaixar' : '⬆️ admin' ?></button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir usuário e TODOS os dados dele?')"><?= csrf_field() ?><input type="hidden" name="form" value="u_del"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
        <?php else: ?><span class="muted">(você)</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php elseif ($tab === 'comentarios'):
    $cs = $pdo->query('SELECT c.*, u.nome, q.concurso, q.ano FROM comentarios c JOIN users u ON u.id = c.user_id JOIN questoes q ON q.id = c.questao_id ORDER BY c.id DESC LIMIT 100')->fetchAll();
?>
<div class="card"><h3>💬 Comentários (<?= count($cs) ?>)</h3>
  <div class="table-wrap"><table class="table">
    <tr><th>ID</th><th>Autor</th><th>Questão</th><th>Texto</th><th>Visível</th><th>Ações</th></tr>
    <?php foreach ($cs as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td><td><?= e($c['nome']) ?></td>
      <td><a href="<?= e(url('resolver.php?id=' . $c['questao_id'])) ?>"><?= e($c['concurso']) ?> <?= (int)$c['ano'] ?></a></td>
      <td><?= e(mb_substr($c['texto'], 0, 120)) ?></td><td><?= $c['aprovado'] ? '✅' : '🚫' ?></td>
      <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form" value="c_toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-small"><?= $c['aprovado'] ? '🙈 ocultar' : '👁️ mostrar' ?></button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir comentário?')"><?= csrf_field() ?><input type="hidden" name="form" value="c_del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php elseif ($tab === 'videos'):
    $editV = null;
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM videoaulas WHERE id = ?');
        $st->execute([(int)$_GET['edit']]);
        $editV = $st->fetch() ?: null;
    }
    $vs = $pdo->query('SELECT * FROM videoaulas ORDER BY id DESC')->fetchAll();
?>
<div class="card">
  <h3><?= $editV ? '✏️ Editar videoaula' : '➕ Nova videoaula' ?></h3>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="form" value="v_save"><input type="hidden" name="id" value="<?= $editV ? (int)$editV['id'] : 0 ?>">
    <label>Título<input name="titulo" value="<?= e($editV['titulo'] ?? '') ?>" required></label>
    <label>URL<input name="vurl" value="<?= e($editV['url'] ?? '') ?>" placeholder="https://..."></label>
    <div class="grid-2">
      <label>Concurso<select name="concurso"><?php foreach (CONCURSOS_TODOS as $c): ?><option <?= ($editV['concurso'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label>Matéria<select name="materia"><?php foreach (MATERIAS as $m): ?><option <?= ($editV['materia'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Descrição<input name="descricao" value="<?= e($editV['descricao'] ?? '') ?>"></label>
    <button class="btn btn-gold" type="submit">💾 Salvar</button>
    <?php if ($editV): ?><a class="btn" href="<?= e(url('admin/index.php?tab=videos')) ?>">Cancelar</a><?php endif; ?>
  </form>
</div>
<div class="card"><div class="table-wrap"><table class="table">
  <tr><th>ID</th><th>Título</th><th>Concurso</th><th>Matéria</th><th>Ações</th></tr>
  <?php foreach ($vs as $v): ?>
  <tr><td><?= (int)$v['id'] ?></td><td><?= e($v['titulo']) ?></td><td><?= e($v['concurso']) ?></td><td><?= e($v['materia']) ?></td>
  <td style="white-space:nowrap">
    <a class="btn btn-small" href="<?= e(url('admin/index.php?tab=videos&edit=' . $v['id'])) ?>">✏️</a>
    <form method="post" style="display:inline" onsubmit="return confirm('Excluir?')"><?= csrf_field() ?><input type="hidden" name="form" value="v_del"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
  </td></tr>
  <?php endforeach; ?>
</table></div></div>

<?php elseif ($tab === 'trilhas'):
    $ts = $pdo->query('SELECT * FROM trilhas ORDER BY id')->fetchAll();
?>
  <?php foreach ($ts as $t):
      $st = $pdo->prepare('SELECT * FROM trilha_modulos WHERE trilha_id = ? ORDER BY ordem, id');
      $st->execute([$t['id']]);
      $mods = $st->fetchAll();
  ?>
  <div class="card">
    <h3><?= e($t['icone'] . ' ' . $t['nome']) ?> <?= $t['ativo'] ? '' : '(inativa)' ?>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form" value="t_toggle"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-small"><?= $t['ativo'] ? '🚫 desativar' : '✅ ativar' ?></button></form>
    </h3>
    <ol>
      <?php foreach ($mods as $m): ?>
      <li><?= e($m['titulo']) ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir módulo?')"><?= csrf_field() ?><input type="hidden" name="form" value="t_mod_del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
      </li>
      <?php endforeach; ?>
    </ol>
    <form method="post" class="form" style="display:flex;gap:8px;align-items:end">
      <?= csrf_field() ?><input type="hidden" name="form" value="t_mod_add"><input type="hidden" name="trilha_id" value="<?= (int)$t['id'] ?>">
      <label style="flex:1;margin:0">Novo módulo<input name="titulo" required placeholder="Ex.: Revisão + simulado"></label>
      <button class="btn btn-small btn-gold" type="submit">➕ Adicionar</button>
    </form>
  </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'importar'):
    include __DIR__ . '/importar_tab.php';
?>

<?php elseif ($tab === 'stats'):
    $dif = $pdo->query('SELECT q.id, q.concurso, q.ano, q.materia, COUNT(*) t, COALESCE(SUM(r.correta),0) ok FROM respostas r JOIN questoes q ON q.id = r.questao_id GROUP BY q.id ORDER BY t DESC LIMIT 10')->fetchAll();
    $ativos = ranking_rows(date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 00:00:00', strtotime('monday next week')), null, 10);
    $corte = date('Y-m-d 00:00:00', strtotime('-13 days'));
    $st = $pdo->prepare('SELECT created_at FROM respostas WHERE created_at >= ?');
    $st->execute([$corte]);
    $porDia = [];
    for ($i = 13; $i >= 0; $i--) $porDia[date('Y-m-d', strtotime("-$i day"))] = 0;
    $maxD = 1;
    while ($row = $st->fetch()) {
        $d = substr((string)$row['created_at'], 0, 10);
        if (isset($porDia[$d])) { $porDia[$d]++; $maxD = max($maxD, $porDia[$d]); }
    }
?>
<div class="grid-2">
  <div class="card"><h3>🎯 Questões mais respondidas (top 10)</h3>
    <div class="table-wrap"><table class="table"><tr><th>Questão</th><th>Tent.</th><th>% acerto</th></tr>
    <?php foreach ($dif as $d): ?>
      <tr><td><a href="<?= e(url('resolver.php?id=' . $d['id'])) ?>"><?= e($d['concurso']) ?> <?= (int)$d['ano'] ?> · <?= e($d['materia']) ?></a></td>
      <td><?= (int)$d['t'] ?></td><td><b><?= (int)round($d['ok'] * 100 / max(1, $d['t'])) ?>%</b></td></tr>
    <?php endforeach; ?>
    </table></div>
  </div>
  <div class="card"><h3>🔥 Mais ativos na semana</h3>
    <div class="table-wrap"><table class="table"><tr><th>Aluno</th><th>Horas</th><th>Questões</th></tr>
    <?php foreach ($ativos as $a): ?>
      <tr><td><?= e($a['nome']) ?></td><td><?= e(fmt_duracao((int)$a['seg'])) ?></td><td><?= (int)$a['q'] ?></td></tr>
    <?php endforeach; ?>
    </table></div>
  </div>
</div>
<div class="card"><h3>📊 Respostas por dia (14 dias)</h3>
  <?php foreach ($porDia as $d => $n): ?>
    <div class="bar-row"><span><?= e(date('d/m', strtotime($d))) ?></span><div class="bar"><div class="bar-fill" style="width:<?= (int)round($n * 100 / $maxD) ?>%"></div></div><b><?= $n ?></b></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
