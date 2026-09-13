<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$pdo = db();

// ---------- ações de grupo (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'criar') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $desc = trim((string)($_POST['descricao'] ?? ''));
        if (mb_strlen($nome) >= 3) {
            do {
                $cod = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $st = $pdo->prepare('SELECT id FROM grupos WHERE codigo = ?');
                $st->execute([$cod]);
            } while ($st->fetch());
            $pdo->prepare('INSERT INTO grupos (nome, codigo, descricao, dono_id) VALUES (?,?,?,?)')->execute([$nome, $cod, $desc, $uid]);
            $gid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?,?)')->execute([$gid, $uid]);
            flash('success', "Grupo \"$nome\" criado! Código: $cod 🎖️");
            redirect('grupo.php?id=' . $gid);
        }
        flash('error', 'Nome do grupo muito curto.');
    } elseif ($acao === 'entrar') {
        $cod = strtoupper(trim((string)($_POST['codigo'] ?? '')));
        $st = $pdo->prepare('SELECT * FROM grupos WHERE codigo = ?');
        $st->execute([$cod]);
        $g = $st->fetch();
        if (!$g) flash('error', 'Código não encontrado.');
        else {
            $st = $pdo->prepare('SELECT 1 FROM grupo_membros WHERE grupo_id = ? AND user_id = ?');
            $st->execute([$g['id'], $uid]);
            if (!$st->fetch()) $pdo->prepare('INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?,?)')->execute([$g['id'], $uid]);
            flash('success', 'Você entrou no grupo "' . $g['nome'] . '"! 🎖️');
            redirect('grupo.php?id=' . $g['id']);
        }
    } elseif ($acao === 'sair') {
        $gid = (int)($_POST['grupo_id'] ?? 0);
        $pdo->prepare('DELETE FROM grupo_membros WHERE grupo_id = ? AND user_id = ?')->execute([$gid, $uid]);
        flash('info', 'Você saiu do grupo.');
        redirect('ranking.php?p=' . urlencode((string)($_GET['p'] ?? 'semana')));
    }
}

// ---------- período ----------
$p = (string)($_GET['p'] ?? 'semana');
if (!in_array($p, ['hoje', 'semana', 'mes'], true)) $p = 'semana';
if ($p === 'hoje') {
    $desde = date('Y-m-d 00:00:00');
    $ate = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $label = 'hoje';
} elseif ($p === 'mes') {
    $desde = date('Y-m-01 00:00:00');
    $ate = date('Y-m-01 00:00:00', strtotime('first day of next month'));
    $label = 'no mês';
} else {
    $desde = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $ate = date('Y-m-d 00:00:00', strtotime('monday next week'));
    $label = 'na semana';
}
$rows = ranking_rows($desde, $ate, null, 20);

// meus grupos
$st = $pdo->prepare('SELECT g.*, (SELECT COUNT(*) FROM grupo_membros m WHERE m.grupo_id = g.id) nm FROM grupos g JOIN grupo_membros gm ON gm.grupo_id = g.id WHERE gm.user_id = ? ORDER BY g.nome');
$st->execute([$uid]);
$meusGrupos = $st->fetchAll();

$title = 'Ranking e grupos';
$active = 'ranking';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>🏆 Ranking e grupos</h1></div>

<div class="tabs">
  <a class="tab <?= $p === 'hoje' ? 'active' : '' ?>" href="<?= e(url('ranking.php?p=hoje')) ?>">⏱️ Hoje</a>
  <a class="tab <?= $p === 'semana' ? 'active' : '' ?>" href="<?= e(url('ranking.php?p=semana')) ?>">🗓️ Semana</a>
  <a class="tab <?= $p === 'mes' ? 'active' : '' ?>" href="<?= e(url('ranking.php?p=mes')) ?>">📅 Mês</a>
</div>

<div class="card">
  <h3>🌍 Ranking geral — horas estudadas <?= e($label) ?></h3>
  <div class="table-wrap"><table class="table">
    <tr><th>#</th><th>Aluno</th><th>Foco</th><th>Horas</th><th>Questões</th><th>🔥</th></tr>
    <?php foreach ($rows as $i => $r):
        $pos = $i + 1;
        $medal = $pos === 1 ? 'medal-gold' : ($pos === 2 ? 'medal-silver' : ($pos === 3 ? 'medal-bronze' : ''));
    ?>
    <tr <?= $r['id'] == $uid ? 'style="background:#fff4d6"' : '' ?>>
      <td><span class="rank-pos <?= $medal ?>"><?= $pos ?>º</span></td>
      <td><b><?= e($r['nome']) ?></b><?= $r['id'] == $uid ? ' (você)' : '' ?></td>
      <td><?= concurso_icone($r['foco']) ?> <?= e($r['foco']) ?></td>
      <td><b><?= e(fmt_duracao((int)$r['seg'])) ?></b></td>
      <td><?= (int)$r['q'] ?></td>
      <td><?= (int)$r['ofensiva'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>👥 Meus grupos (<?= count($meusGrupos) ?>)</h3>
    <?php if (!$meusGrupos): ?><p class="muted">Você ainda não está em nenhum grupo. Crie um ou entre com um código!</p><?php endif; ?>
    <?php foreach ($meusGrupos as $g): ?>
      <p>🛡️ <a href="<?= e(url('grupo.php?id=' . $g['id'])) ?>"><b><?= e($g['nome']) ?></b></a>
      <span class="muted">(<?= (int)$g['nm'] ?> membros · código <code><?= e($g['codigo']) ?></code>)</span></p>
    <?php endforeach; ?>
    <h3 style="margin-top:16px">➕ Criar grupo</h3>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="criar">
      <label>Nome do grupo <input name="nome" required placeholder="Ex.: Esquadrão EFOMM 2026"></label>
      <label>Descrição <input name="descricao" placeholder="Ex.: Rumo à Marinha Mercante!"></label>
      <button class="btn btn-gold" type="submit">Criar grupo</button>
    </form>
  </div>
  <div class="card">
    <h3>🔑 Entrar com código</h3>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="entrar">
      <label>Código do grupo <input name="codigo" required placeholder="Ex.: A1B2C3" style="text-transform:uppercase"></label>
      <button class="btn" type="submit">Entrar no grupo</button>
    </form>
    <h3 style="margin-top:16px">💡 Como funciona?</h3>
    <p class="muted">O ranking ordena por <b>horas estudadas</b> no período (critério de desempate: questões resolvidas). Estude com o timer ligado, resolva questões e suba de posição. Chame seus amigos com o código do grupo!</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
