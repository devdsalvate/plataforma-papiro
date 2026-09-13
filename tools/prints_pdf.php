#!/usr/bin/env php
<?php
declare(strict_types=1);
/* ============================================================
   Papiro Máximo — Gerar prints (PNG) das páginas de um PDF
   Uso: php tools/prints_pdf.php simulados/arquivo.pdf [--dpi=150] [--max=40]
   Precisa de pdftoppm (poppler-utils) ou Ghostscript instalados.
   Em hospedagem sem eles: rode localmente e envie os PNGs pela
   aba Admin -> Importar PDFs ("Enviar prints").
   ============================================================ */
if (PHP_SAPI !== 'cli') exit("Use via terminal.\n");
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/import_lib.php';

$args = array_slice($argv, 1);
$dpi = 150;
$max = 40;
$file = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--dpi=')) $dpi = (int)substr($a, 6);
    elseif (str_starts_with($a, '--max=')) $max = (int)substr($a, 6);
    elseif (!str_starts_with($a, '--')) $file = $a;
}
if ($file === null || !is_file($file)) exit("Informe um PDF. Ex.: php tools/prints_pdf.php simulados/arquivo.pdf\n");

import_migrate();
echo 'Backend: ' . (prints_backend() ?? 'NENHUM (instale poppler-utils ou ghostscript)') . "\n";
$r = prints_generate($file, basename($file), $dpi, $max);
if (!$r['ok']) exit('❌ ' . $r['erro'] . "\n");
echo '📸 ' . $r['paginas'] . ' página(s) renderizada(s) via ' . $r['backend'] . ". ✅\n";
