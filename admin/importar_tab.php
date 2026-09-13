<?php
/* Papiro Máximo — Tab Admin → Importar PDFs.
   Incluído por admin/index.php (já tem $pdo, $user, require_admin). */
require_once dirname(__DIR__) . '/includes/import_lib.php';
import_migrate();
@set_time_limit(300);

$dir = import_simulados_dir();
$result = null;
$preview = null;
$previewFile = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $form = (string)($_POST['form'] ?? '');

    if ($form === 'upload' && isset($_FILES['pdf'])) {
        $f = $_FILES['pdf'];
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($f['name'] ?? ''));
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) flash('error', 'Falha no upload.');
        elseif (strtolower(substr($name, -4)) !== '.pdf') flash('error', 'Envie um arquivo .pdf.');
        elseif (($f['size'] ?? 0) > 20 * 1024 * 1024) flash('error', 'Máximo 20 MB por arquivo.');
        else {
            move_uploaded_file($f['tmp_name'], $dir . '/' . $name);
            flash('success', "Arquivo $name enviado! ✅");
        }
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'preview') {
        $arq = basename((string)($_POST['arquivo'] ?? ''));
        $p = $dir . '/' . $arq;
        if (is_file($p)) {
            $preview = pdf_text($p);
            $previewFile = $arq;
        }
    } elseif ($form === 'process') {
        $arq = basename((string)($_POST['arquivo'] ?? ''));
        $p = $dir . '/' . $arq;
        if (!is_file($p)) {
            flash('error', 'Arquivo não encontrado.');
            redirect('admin/index.php?tab=importar');
        }
        // não processa na hora: cria um trabalho em 2º plano (a página não trava; pode sair e voltar)
        $jobId = job_criar($arq, (int)$user['id'], [
            'concurso' => in_array($_POST['concurso'] ?? '', CONCURSOS_TODOS, true) ? (string)$_POST['concurso'] : '',
            'ano' => ctype_digit((string)($_POST['ano'] ?? '')) ? (int)$_POST['ano'] : 0,
        ]);
        if ($jobId <= 0) flash('error', 'Não deu para criar o trabalho (PDF sem texto legível?).');
    } elseif ($form === 'remapear') {
        $arq = basename((string)($_POST['arquivo'] ?? ''));
        $p = $dir . '/' . $arq;
        if (!is_file($p)) {
            flash('error', 'Arquivo não encontrado.');
            redirect('admin/index.php?tab=importar');
        }
        $rm = import_remapear($p, $arq);
        flash($rm['ok'] ? 'success' : 'error', $rm['ok'] ? ("📍 Páginas remapeadas (sem gastar IA): {$rm['pagina']} com página, {$rm['regiao']} com recorte, {$rm['estimada']} aproximadas.") : $rm['erro']);
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'destravar') {
        $jid = (int)($_POST['job'] ?? 0);
        $jb = $jid > 0 ? job_get($jid) : null;
        // só destrava 'rodando' parado há 90s+ (a requisição morreu no meio do passo); nunca mexe em dados
        if ($jb && $jb['status'] === 'rodando' && (time() - strtotime((string)$jb['updated_at'])) > 90) {
            $pdo->prepare("UPDATE import_jobs SET status='pausado', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$jid]);
            flash('success', '🔓 Trabalho destravado — ele continua sozinho no painel abaixo.');
        } else {
            flash('error', 'Nada para destravar (o botão só aparece quando um passo morre há mais de 90s).');
        }
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'ativar') {
        $n = import_ativar(basename((string)($_POST['arquivo'] ?? '')));
        flash('success', "$n questão(ões) ativadas e visíveis p/ os alunos! ✅");
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'excluir') {
        $n = import_excluir(basename((string)($_POST['arquivo'] ?? '')));
        flash('info', "$n questão(ões) importadas foram excluídas.");
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'img_assign') {
        $iid = (int)($_POST['img_id'] ?? 0);
        $qq = (int)($_POST['questao_id'] ?? 0);
        if ($iid > 0 && $qq > 0) {
            $pdo->prepare("UPDATE questao_imagens SET questao_id = ?, legenda = '' WHERE id = ?")->execute([$qq, $iid]);
            flash('success', 'Imagem vinculada à questão! ✅');
        }
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'img_del') {
        questao_excluir_imagem((int)($_POST['img_id'] ?? 0));
        flash('info', 'Imagem excluída.');
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'prints') {
        $arq = basename((string)($_POST['arquivo'] ?? ''));
        $p = $dir . '/' . $arq;
        if (!is_file($p)) {
            flash('error', 'Arquivo não encontrado.');
            redirect('admin/index.php?tab=importar');
        }
        @set_time_limit(300);
        $rp = prints_generate($p, $arq);
        flash($rp['ok'] ? 'success' : 'error', $rp['ok'] ? ("📸 {$rp['paginas']} página(s) renderizada(s)! ✅") : $rp['erro']);
        redirect('admin/index.php?tab=importar');
    } elseif ($form === 'prints_upload') {
        $arq = basename((string)($_POST['arquivo'] ?? ''));
        $ok = 0;
        if ($arq !== '' && !empty($_FILES['prints']['name'][0])) {
            $pdir = import_prints_dir();
            $slug = prints_slug($arq);
            $pdo->prepare('DELETE FROM import_paginas WHERE arquivo = ?')->execute([$arq]);
            foreach (glob($pdir . '/' . $slug . '-*.*') ?: [] as $old) @unlink($old);
            $ins = $pdo->prepare('INSERT INTO import_paginas (arquivo, pagina, arquivo_img) VALUES (?,?,?)');
            $seq = 0;
            foreach ($_FILES['prints']['name'] as $i => $nm) {
                if (($_FILES['prints']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
                $tmp = $_FILES['prints']['tmp_name'][$i];
                $info = @getimagesize($tmp);
                if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) continue;
                $ext = ($info['mime'] === 'image/png') ? 'png' : 'jpg';
                $pg = preg_match('/-p?(\d{1,3})\.(png|jpe?g)$/i', (string)$nm, $m) ? (int)$m[1] : 0;
                if ($pg <= 0) $pg = ++$seq;
                else $seq = max($seq, $pg);
                $fn = $slug . '-' . $pg . '.' . $ext;
                if (move_uploaded_file($tmp, $pdir . '/' . $fn)) {
                    $pdo->prepare('DELETE FROM import_paginas WHERE arquivo = ? AND pagina = ?')->execute([$arq, $pg]);
                    $ins->execute([$arq, $pg, $fn]);
                    $ok++;
                }
            }
        }
        flash($ok > 0 ? 'success' : 'error', $ok > 0 ? "📸 $ok print(s) registrado(s)! ✅" : 'Nenhum print válido.');
        redirect('admin/index.php?tab=importar');
    }
}

$files = glob($dir . '/*.pdf') ?: [];
sort($files);
$imps = [];
foreach ($pdo->query('SELECT * FROM importacoes ORDER BY id DESC')->fetchAll() as $r) $imps[$r['arquivo']] = $r;
?>

<div class="card">
  <h3>📥 Como funciona</h3>
  <ol class="muted">
    <li>Coloque os PDFs na pasta <code>simulados/</code> (ou envie abaixo).</li>
    <li>Confira com <b>👁️ Ver texto</b> se o PDF é legível (digitalizado não funciona).</li>
    <li>Clique em <b>🤖 Processar</b>: a IA identifica <b>concurso + ano</b> e lança as questões no formatinho do banco, em <b>2º plano</b> (a página não trava — pode sair e voltar que continua de onde parou).</li>
    <li>Questões sem página/recorte? Use o botão <b>📍</b> do arquivo: re-mapeia páginas e recortes <b>sem gastar IA</b>.</li>
    <li>Elas entram como <b>rascunho</b> — revise em <a href="<?= e(url('admin/index.php?tab=questoes')) ?>">Questões</a> e depois <b>✅ Ative</b>.</li>
  </ol>
  <?php if (GEMINI_API_KEY === '' && MISTRAL_API_KEY === '' && GROQ_API_KEY === ''): ?>
    <div class="flash info">⚠️ Sem chave de IA: dá para enviar PDFs e ver o texto, mas o processamento fica indisponível. Configure uma chave em <code>includes/config.local.php</code> (veja o README).</div>
  <?php endif; ?>
  <?php $pb = prints_backend(); ?>
  <p class="muted">🖨️ <b>Print automático:</b> o site gera sozinho o print da página de cada questão no navegador do aluno (PDF.js embutido — funciona em qualquer hospedagem, sem instalar nada). 📸 Os <b>prints em PNG</b> são só um bônus (carregam mais rápido e funcionam offline): <?= $pb ? "renderizador <b>$pb</b> disponível ✅ — use o botão 📸 em cada arquivo" : 'sem renderizador neste servidor — se quiser os PNGs, gere localmente com <code>php tools/prints_pdf.php arquivo.pdf</code> e envie abaixo' ?></p>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:12px">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="prints_upload">
    <label style="margin:0">PDF<select name="arquivo"><?php foreach ($files as $fp): ?><option value="<?= e(basename($fp)) ?>"><?= e(basename($fp)) ?></option><?php endforeach; ?></select></label>
    <label style="margin:0">Prints (nomeie: algo-p1.png, algo-p2.png...)<input type="file" name="prints[]" multiple accept=".png,.jpg,.jpeg" required></label>
    <button class="btn" type="submit">📤 Enviar prints</button>
  </form>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="upload">
    <label style="margin:0">Enviar PDF (máx. 20 MB)<input type="file" name="pdf" accept=".pdf,application/pdf" required></label>
    <button class="btn btn-gold" type="submit">📤 Enviar</button>
  </form>
</div>

<?php if ($previewFile !== null): ?>
<div class="card">
  <h3>👁️ Texto extraído de <code><?= e($previewFile) ?></code> (<?= strlen((string)$preview) ?> chars)</h3>
  <?php if (trim((string)$preview) === ''): ?>
    <div class="flash error">Nenhum texto legível — este PDF deve ser digitalizado (imagem) ou protegido.</div>
  <?php else: ?>
    <pre style="white-space:pre-wrap;background:#f7f2e2;border:1px solid var(--line);border-radius:10px;padding:12px;max-height:320px;overflow:auto"><?= e(mb_substr((string)$preview, 0, 4000)) ?><?= strlen((string)$preview) > 4000 ? "\n…(cortado na prévia)" : '' ?></pre>
    <p class="muted">Se o texto acima está legível, a IA consegue processar. ✅</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Trabalhos em 2º plano: a página nunca trava; pode sair e voltar que retoma do ponto onde parou.
$jobs = [];
try {
    $jobs = $pdo->query("SELECT * FROM import_jobs WHERE status <> 'concluido' ORDER BY id DESC LIMIT 10")->fetchAll();
} catch (Throwable $e) { $jobs = []; }
?>
<?php foreach ($jobs as $jb): ?>
<div class="card" data-jobcard="<?= (int)$jb['id'] ?>">
  <h3>🤖 Importando em 2º plano: <code><?= e($jb['arquivo']) ?></code> <span data-jobstatus class="badge b-gold"><?= e($jb['status']) ?></span></h3>
  <div style="background:#e8e2cf;border-radius:8px;height:14px;overflow:hidden"><div data-jobbar style="height:100%;width:0%;background:#c9a227;transition:width .4s"></div></div>
  <p data-jobmsg class="muted">Preparando... (pode sair desta página e voltar depois — não perde nada)</p>
  <div data-jobdone style="display:none"></div>
  <?php if ($jb['status'] === 'rodando' && (time() - strtotime((string)$jb['updated_at'])) > 90): ?>
  <form method="post" style="margin-top:6px" onsubmit="return confirm('Destravar? Use quando o passo morreu (o servidor matou a requisição no meio). Não apaga nada.')"><?= csrf_field() ?><input type="hidden" name="form" value="destravar"><input type="hidden" name="job" value="<?= (int)$jb['id'] ?>"><button class="btn btn-small" type="submit">🔓 Destravar (passo parado há 90s+)</button></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($jobs): ?>
<script>
(function(){
  var csrfEl = document.querySelector('input[name=csrf]');
  var csrf = csrfEl ? csrfEl.value : '';
  var api = <?= json_encode(url('api.php')) ?>;
  function step(card){
    var id = card.getAttribute('data-jobcard');
    var bar = card.querySelector('[data-jobbar]');
    var msg = card.querySelector('[data-jobmsg]');
    var done = card.querySelector('[data-jobdone]');
    var st = card.querySelector('[data-jobstatus]');
    var fd = new FormData();
    fd.append('action','job_step'); fd.append('csrf',csrf); fd.append('id',id);
    fetch(api, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(j){
      if (!j.ok && j.busy) { msg.textContent = '⏳ Outra aba/processo está rodando este trabalho... tentando de novo em 5s.'; setTimeout(function(){ step(card); }, 5000); return; }
      if (!j.ok) { st.textContent = 'erro'; st.className = 'badge b-red'; msg.textContent = '❌ ' + (j.error || 'falha'); return; }
      var pct = j.total > 0 ? Math.round(j.feitos / j.total * 100) : 0;
      bar.style.width = pct + '%';
      msg.innerHTML = j.msgHtml || '';
      if (j.done) {
        st.textContent = 'concluído'; st.className = 'badge b-green';
        bar.style.width = '100%';
        done.style.display = '';
        done.innerHTML = j.doneHtml || '';
      } else {
        step(card);
      }
    }).catch(function(){ msg.textContent = '⚠️ Conexão falhou — recarregue a página para retomar de onde parou.'; });
  }
  document.querySelectorAll('[data-jobcard]').forEach(function(card){ step(card); });
})();
</script>
<?php endif; ?>
<?php if (false && $result && $result['ok']): // LEGADO desativado: fluxo direto trocado por trabalhos em 2º plano ?>
<div class="card">
  <h3>🤖 Resultado: <code><?= e($result['arquivo']) ?></code></h3>
  <p>Identificado: <b><?= concurso_icone($result['concurso']) ?> <?= e(concurso_nome($result['concurso'])) ?> <?= $result['ano'] > 0 ? (int)$result['ano'] : '' ?></b>
    · <?= (int)$result['chars'] ?> chars · <?= (int)$result['processados'] ?>/<?= (int)$result['trechos'] ?> trechos</p>
  <p>✅ <b><?= count($result['ids']) ?> questões salvas como rascunho</b> (total: <?= (int)($result['total_salvas'] ?? count($result['ids'])) ?>)<?= $result['puladas'] > 0 ? " · ⚠️ {$result['puladas']} ignoradas" : '' ?></p>
  <?php if (($result['esperadas'] ?? 0) > 0): ?>
    <p>📊 O texto parece ter <b>~<?= (int)$result['esperadas'] ?> questões</b> ·
    <?php if ((int)$result['total_salvas'] >= (int)$result['esperadas']): ?>pegou tudo ✅
    <?php else: ?>⚠️ <b>faltam <?= (int)$result['esperadas'] - (int)$result['total_salvas'] ?></b> — confira os erros/motivos abaixo<?php endif; ?></p>
  <?php endif; ?>
  <?php if (!empty($result['motivos'])): ?>
    <p class="muted">🔍 Por que ignorou: <?= e(implode(' · ', $result['motivos'])) ?></p>
  <?php endif; ?>
  <?php if (($result['falta'] ?? 0) > 0): ?>
    <div class="flash info">⏭ Faltam <b><?= (int)$result['falta'] ?></b> trecho(s) — continuando sozinho em 3s... <a href="<?= e(url('admin/index.php?tab=importar')) ?>">⏸ Parar</a></div>
    <form method="post" id="contForm">
      <?= csrf_field() ?><input type="hidden" name="form" value="process"><input type="hidden" name="arquivo" value="<?= e($result['arquivo']) ?>">
      <input type="hidden" name="inicio" value="<?= (int)$result['proximo'] ?>">
      <input type="hidden" name="max_chunks" value="<?= (int)($_POST['max_chunks'] ?? 2) ?>">
      <input type="hidden" name="concurso" value="<?= e($result['concurso']) ?>"><input type="hidden" name="ano" value="<?= (int)$result['ano'] ?>">
      <button class="btn btn-small btn-gold" type="submit">▶ Continuar agora (trecho <?= (int)$result['proximo'] + 1 ?> de <?= (int)$result['trechos'] ?>)</button>
    </form>
    <script>setTimeout(function(){document.getElementById('contForm').submit();},3000);</script>
  <?php endif; ?>
  <?php if (($result['imagens'] ?? 0) > 0): ?>
    <p>🖼️ <b><?= (int)$result['imagens'] ?> imagem(ns) extraída(s)</b> · <?= (int)$result['img_ligadas'] ?> vinculada(s) automaticamente<?= ($result['imagens'] - $result['img_ligadas']) > 0 ? ' · <b>' . ((int)$result['imagens'] - (int)$result['img_ligadas']) . ' aguardando vínculo abaixo</b>' : '' ?></p>
  <?php endif; ?>
  <?php if ($result['erros']): ?>
    <p class="muted">Erros: <?= e(implode(' | ', $result['erros'])) ?></p>
  <?php endif; ?>
  <?php if ($result['ids']): ?>
    <p>Revisar:
      <?php foreach (array_slice($result['ids'], 0, 20) as $qid): ?>
        <a class="badge b-gold" href="<?= e(url('admin/index.php?tab=questoes&edit=' . $qid)) ?>">#<?= $qid ?></a>
      <?php endforeach; ?>
      <?= count($result['ids']) > 20 ? '…' : '' ?>
    </p>
    <form method="post" style="display:inline" onsubmit="return confirm('Ativar todas e liberar p/ os alunos?')">
      <?= csrf_field() ?><input type="hidden" name="form" value="ativar"><input type="hidden" name="arquivo" value="<?= e($result['arquivo']) ?>">
      <button class="btn btn-ok" type="submit">✅ Ativar todas</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3>🖼️ Imagens aguardando vínculo</h3>
  <?php
  $pend = $pdo->query('SELECT * FROM questao_imagens WHERE questao_id IS NULL ORDER BY origem, id')->fetchAll();
  if (!$pend): ?>
    <p class="muted">Nenhuma. Figuras extraídas dos PDFs que não deu para vincular sozinho aparecem aqui com miniatura.</p>
  <?php else:
    $qsByOrig = [];
    foreach ($pend as $p) {
        if (!isset($qsByOrig[$p['origem']])) {
            $st = $pdo->prepare('SELECT id, numero, enunciado FROM questoes WHERE origem = ? ORDER BY numero, id');
            $st->execute([$p['origem']]);
            $qsByOrig[$p['origem']] = $st->fetchAll();
        }
    }
  ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
  <?php foreach ($pend as $p): ?>
    <div style="border:1px solid var(--line);border-radius:10px;padding:8px;width:210px">
      <a href="<?= e(url('assets/uploads/questoes/' . $p['arquivo'])) ?>" target="_blank"><img src="<?= e(url('assets/uploads/questoes/' . $p['arquivo'])) ?>" style="width:100%;border-radius:6px"></a>
      <div class="muted"><?= e($p['origem']) ?></div>
      <form method="post" style="display:flex;gap:4px;margin-top:6px">
        <?= csrf_field() ?><input type="hidden" name="form" value="img_assign"><input type="hidden" name="img_id" value="<?= (int)$p['id'] ?>">
        <select name="questao_id" style="flex:1;padding:5px;min-width:0">
          <?php foreach ($qsByOrig[$p['origem']] as $qq): ?>
          <option value="<?= (int)$qq['id'] ?>">#<?= (int)$qq['id'] ?><?= $qq['numero'] > 0 ? ' (Q' . (int)$qq['numero'] . ')' : '' ?> — <?= e(mb_substr(strip_tags($qq['enunciado']), 0, 28)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-small btn-gold" title="Vincular">🔗</button>
      </form>
      <form method="post" onsubmit="return confirm('Excluir imagem?')"><?= csrf_field() ?><input type="hidden" name="form" value="img_del"><input type="hidden" name="img_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-small btn-danger" style="margin-top:4px">🗑️ Excluir</button></form>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>📄 Arquivos em <code>simulados/</code> (<?= count($files) ?>)</h3>
  <?php if (!$files): ?>
    <p class="muted">Nenhum PDF ainda. Envie acima ou copie os arquivos para a pasta <code>simulados/</code>.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <tr><th>Arquivo</th><th>Tamanho</th><th>Status</th><th>Processar</th><th>Ações</th></tr>
    <?php foreach ($files as $fp):
        $arq = basename($fp);
        $imp = $imps[$arq] ?? null;
    ?>
    <tr>
      <td><b><?= e($arq) ?></b></td>
      <td><?= number_format(filesize($fp) / 1024, 0, ',', '.') ?> KB</td>
      <td>
        <?php if (!$imp): ?><span class="badge">não processado</span>
        <?php elseif ($imp['status'] === 'ativo'): ?><span class="badge b-green">✅ ativo · <?= (int)$imp['questoes'] ?> q.</span>
        <?php else: ?><span class="badge b-gold">📝 rascunho · <?= (int)$imp['questoes'] ?> q.</span>
        <?php endif; ?>
        <?php if ($imp): ?><br><span class="muted"><?= concurso_icone($imp['concurso']) ?> <?= e(concurso_nome($imp['concurso'])) ?> <?= $imp['ano'] > 0 ? (int)$imp['ano'] : '' ?></span><?php endif; ?>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:end">
          <?= csrf_field() ?><input type="hidden" name="form" value="process"><input type="hidden" name="arquivo" value="<?= e($arq) ?>">
          <label class="muted">Concurso<select name="concurso" style="padding:5px">
            <option value="">🤖 auto</option>
            <?php foreach (CONCURSOS_TODOS as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
          </select></label>
          <label class="muted">Ano<input name="ano" placeholder="auto" style="width:70px;padding:5px"></label>
          <button class="btn btn-small btn-gold" type="submit" <?= GROQ_API_KEY === '' ? 'disabled title="Configure a GROQ_API_KEY"' : '' ?>>🤖 Processar</button>
        </form>
      </td>
      <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form" value="preview"><input type="hidden" name="arquivo" value="<?= e($arq) ?>"><button class="btn btn-small">👁️</button></form>
        <?php if ($pb): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Renderizar prints de todas as páginas? Pode demorar um pouco.')"><?= csrf_field() ?><input type="hidden" name="form" value="prints"><input type="hidden" name="arquivo" value="<?= e($arq) ?>"><button class="btn btn-small" title="Gerar prints das páginas em PNG (bônus: mais rápido)">📸</button></form>
        <?php else: ?>
        <span class="badge b-green" title="Sem renderizador neste servidor — mas não precisa: os alunos já veem o print automático da página, gerado no navegador. PNG é só um bônus opcional.">🖨️ auto ✅</span>
        <?php endif; ?>
        <?php if ($imp && $imp['status'] !== 'ativo'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Ativar todas e liberar p/ os alunos?')"><?= csrf_field() ?><input type="hidden" name="form" value="ativar"><input type="hidden" name="arquivo" value="<?= e($arq) ?>"><button class="btn btn-small btn-ok">✅</button></form>
        <?php endif; ?>
        <?php if ($imp): ?>
        <?php
        $dg = ['t' => 0, 'pg' => 0, 'rg' => 0, 'est' => 0];
        try {
            $dg = $pdo->query("SELECT COUNT(*) t, COALESCE(SUM(pagina > 0),0) pg, COALESCE(SUM(regiao <> '' AND regiao <> '~'),0) rg, COALESCE(SUM(regiao = '~'),0) est FROM questoes WHERE origem = " . $pdo->quote($arq))->fetch() ?: $dg;
        } catch (Throwable $e) { /* coluna regiao ainda não existe */
        }
        ?>
        <div class="muted" style="font-size:.8em">📍 <?= (int)$dg['pg'] ?>/<?= (int)$dg['t'] ?> com página · ✂️ <?= (int)$dg['rg'] ?> com recorte<?= (int)$dg['est'] > 0 ? ' · ~' . (int)$dg['est'] . ' aproximadas' : '' ?></div>
        <form method="post" style="display:inline" title="Re-mapear páginas e recortes sem gastar IA"><?= csrf_field() ?><input type="hidden" name="form" value="remapear"><input type="hidden" name="arquivo" value="<?= e($arq) ?>"><button class="btn btn-small">📍</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir as questões importadas deste arquivo?')"><?= csrf_field() ?><input type="hidden" name="form" value="excluir"><input type="hidden" name="arquivo" value="<?= e($arq) ?>"><button class="btn btn-small btn-danger">🗑️</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  <p class="muted">💡 PDFs grandes? Use o terminal (sem limite de tempo): <code>php tools/importar_pdf.php simulados/arquivo.pdf</code></p>
</div>
