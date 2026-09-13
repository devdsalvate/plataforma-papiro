#!/usr/bin/env php
<?php
declare(strict_types=1);
/* ============================================================
   Papiro Máximo — Importar simulado PDF via terminal
   Uso:
     php tools/importar_pdf.php simulados/arquivo.pdf
     php tools/importar_pdf.php simulados/arquivo.pdf --ativar --max-chunks=30
     php tools/importar_pdf.php simulados/arquivo.pdf --concurso=EFOMM --ano=2024
     php tools/importar_pdf.php simulados/arquivo.pdf --ativar --prints
     php tools/importar_pdf.php --texto simulados/arquivo.pdf   (só mostra o texto)
     php tools/importar_pdf.php simulados/arquivo.pdf --inicio=4   (continua do trecho 5 em diante)
   --prints: renderiza as páginas em PNG (precisa pdftoppm/gs/imagick).
   --inicio=N: continua uma importação interrompida a partir do trecho N+1 (não duplica).
   ============================================================ */
if (PHP_SAPI !== 'cli') exit("Use via terminal.\n");
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/import_lib.php';

$args = array_slice($argv, 1);
$opts = ['max_chunks' => 30];
$onlyText = false;
$file = null;
foreach ($args as $a) {
    if ($a === '--texto') $onlyText = true;
    elseif ($a === '--ativar') $opts['ativar'] = true;
    elseif ($a === '--prints') $opts['prints'] = true;
    elseif (str_starts_with($a, '--max-chunks=')) $opts['max_chunks'] = max(1, (int)substr($a, 13));
    elseif (str_starts_with($a, '--inicio=')) $opts['inicio'] = max(0, (int)substr($a, 9));
    elseif (str_starts_with($a, '--concurso=')) $opts['concurso'] = substr($a, 11);
    elseif (str_starts_with($a, '--ano=')) $opts['ano'] = (int)substr($a, 6);
    elseif (!str_starts_with($a, '--')) $file = $a;
}
if ($file === null || !is_file($file)) {
    exit("Informe um PDF válido. Ex.: php tools/importar_pdf.php simulados/arquivo.pdf\n");
}
if ($onlyText) {
    $t = pdf_text($file);
    echo 'Chars extraídos: ' . strlen($t) . "\n\n" . $t . "\n";
    exit;
}

import_migrate();
$adminId = (int)(db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
$opts['progress'] = function ($i, $n, $q) { echo "  trecho $i/$n... ($q questões até aqui)\n"; };

echo '📄 ' . basename($file) . "\n";
$res = import_process($file, $adminId, $opts);
if (!$res['ok']) exit('❌ ' . $res['erro'] . "\n");

echo '🤖 Identificado: ' . $res['concurso'] . ' ' . ($res['ano'] > 0 ? $res['ano'] : '') . "\n";
echo '✅ ' . count($res['ids']) . ' questões salvas como RASCUNHO (puladas: ' . $res['puladas'] . ', total: ' . ($res['total_salvas'] ?? count($res['ids'])) . ")\n";
if (($res['esperadas'] ?? 0) > 0) echo '📊 Esperadas pelo texto: ~' . $res['esperadas'] . ' · trechos ' . $res['processados'] . '/' . $res['trechos'] . "\n";
foreach (($res['motivos'] ?? []) as $m) echo '  🔍 pulada: ' . $m . "\n";
if (($res['imagens'] ?? 0) > 0) echo '🖼️ ' . $res['imagens'] . ' imagem(ns) extraída(s), ' . $res['img_ligadas'] . " vinculada(s).\n";
if (isset($res['prints']) && ($opts['prints'] ?? false)) {
    echo !empty($res['prints']['ok'])
        ? '📸 ' . $res['prints']['paginas'] . " print(s) gerado(s).\n"
        : '⚠️ Prints: ' . ($res['prints']['erro'] ?? 'falha') . "\n";
}
foreach ($res['erros'] as $e) echo '  ⚠️ ' . $e . "\n";
if (($res['falta'] ?? 0) > 0) echo '⏭ Faltam ' . $res['falta'] . ' trecho(s) — continue com: php tools/importar_pdf.php ' . $file . ' --inicio=' . $res['proximo'] . "\n";
if (!empty($opts['ativar'])) {
    $n = import_ativar($res['arquivo']);
    echo "🚀 $n questões ATIVADAS.\n";
} else {
    echo "💡 Revise no admin e ative (ou rode com --ativar).\n";
}
