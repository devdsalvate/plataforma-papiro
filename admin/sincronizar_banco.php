<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/import_lib.php';
require_once dirname(__DIR__) . '/includes/official_bank.php';
require_once dirname(__DIR__) . '/includes/default_admins.php';
$user = require_admin();
$pdo = db();
import_migrate();
$result = null; $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $erro = 'Sessão expirada. Recarregue a página.';
    else {
        try {
            remove_password_recovery_storage($pdo);
            $result = official_bank_sync($pdo);
            $result['admins'] = ensure_default_admins($pdo);
        }
        catch (Throwable $e) { $erro = $e->getMessage(); }
    }
}
$nQ = 0;
try { $nQ = (int)$pdo->query("SELECT COUNT(*) FROM questoes WHERE ativo = 1")->fetchColumn(); } catch (Throwable $e) {}
$title='Sincronizar acervo'; $active='admin';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head"><div><h1>Sincronizar acervo oficial</h1><div class="muted">Importa ou atualiza as 3.049 questões do pacote sem apagar usuários, respostas ou progresso.</div></div></div>
<?php if($erro!==''): ?><div class="flash error"><?= e($erro) ?></div><?php endif; ?>
<?php if($result): ?><div class="flash success">Acervo sincronizado: <?= (int)$result['total'] ?> registros · <?= (int)$result['inserted'] ?> novos · <?= (int)$result['updated'] ?> atualizados. Contas ADM verificadas: <?= (int)($result['admins']['total'] ?? 0) ?>.</div><?php endif; ?>
<div class="card" style="max-width:760px">
  <h2>Banco atual</h2>
  <p><b><?= number_format($nQ,0,',','.') ?></b> questões ativas no banco.</p>
  <p class="muted">A sincronização usa o arquivo <code>database/official_questions.json</code> incluído neste pacote. Registros oficiais existentes são atualizados pelo slug; contas e histórico não são removidos. A sincronização também garante as contas ADM iniciais e remove a estrutura antiga de recuperação de senha.</p>
  <form method="post"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Sincronizar agora</button> <a class="btn" href="<?= e(url('admin/index.php')) ?>">Voltar ao admin</a></form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
