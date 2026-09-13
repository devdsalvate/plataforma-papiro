<?php
declare(strict_types=1);
/* ============================================================
   PAPIRO MÁXIMO — Extrator de texto de PDF (sem dependências)
   Funciona com PDFs TEXTUAIS (Word/LaTeX/impressão direta).
   NÃO faz OCR: PDF digitalizado (só imagem) não tem texto p/ extrair.
   ============================================================ */

function pdf_win1252_to_utf8(string $s): string {
    if (function_exists('mb_convert_encoding')) {
        $u = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        if (is_string($u)) return $u;
    }
    static $map = [0x80 => '€', 0x82 => '‚', 0x83 => 'ƒ', 0x84 => '„', 0x85 => '…', 0x86 => '†', 0x87 => '‡', 0x88 => 'ˆ', 0x89 => '‰', 0x8A => 'Š', 0x8B => '‹', 0x8C => 'Œ', 0x8E => 'Ž', 0x91 => '‘', 0x92 => '’', 0x93 => '“', 0x94 => '”', 0x95 => '•', 0x96 => '–', 0x97 => '—', 0x98 => '˜', 0x99 => '™', 0x9A => 'š', 0x9B => '›', 0x9C => 'œ', 0x9E => 'ž', 0x9F => 'Ÿ'];
    $out = '';
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $o = ord($s[$i]);
        if ($o < 0x80) $out .= $s[$i];
        elseif (isset($map[$o])) $out .= $map[$o];
        else $out .= chr(0xC0 | ($o >> 6)) . chr(0x80 | ($o & 0x3F));
    }
    return $out;
}

/** Desescapa string literal de PDF: octal \ddd, \( \) \\ \n \r \t */
function pdf_unescape(string $s): string {
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) { return chr(octdec($m[1])); }, $s) ?? $s;
    $s = str_replace(['\\\\', '\\(', '\\)', '\\n', '\\r', '\\t'], ["\x00", '(', ')', "\n", "\r", "\t"], $s);
    $s = preg_replace('/\\\\(.)/s', '$1', $s) ?? $s;
    return str_replace("\x00", '\\', $s);
}

/** Decodifica string hexadecimal <...> (Tj). */
function pdf_hex_text(string $hex): string {
    $hex = preg_replace('/\s+/', '', $hex) ?? '';
    if (strlen($hex) % 2 === 1) $hex .= '0';
    $raw = (string)pack('H*', $hex);
    if (str_starts_with($raw, "\xFE\xFF")) {
        $raw = substr($raw, 2);
        if (function_exists('mb_convert_encoding')) {
            $u = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            if (is_string($u)) return $u;
        }
        return str_replace("\x00", '', $raw);
    }
    return pdf_win1252_to_utf8($raw);
}

/**
 * Reconstrói o texto de um array de TJ ("[(a)-30(b)500(c)]") juntando as
 * strings na ordem certa e — o pulo do gato — inserindo um espaço onde o
 * deslocamento numérico entre duas strings é grande (isso É o espaço entre
 * palavras: muitos geradores de PDF, sobretudo os que "imprimem" documentos
 * de matemática/ciências, nunca colocam um caractere de espaço de verdade;
 * o espaço visual vem só desse reposicionamento). Sem isso, "Considere um
 * octaedro" vira "Considereumoctaedro" — o que também derruba a detecção
 * de "Questão N" (e prejudica a própria IA, que recebe o texto colado).
 * Limiar de -120 (unidades de 1/1000 em): ajustes de kerning entre letras
 * ficam bem abaixo disso; um espaço de palavra real costuma passar de -200.
 */
function pdf_tj_text(string $arrInner): string {
    $out = '';
    if (!preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<[0-9A-Fa-f\s]+>|-?\d+(?:\.\d+)?/', $arrInner, $toks)) return '';
    foreach ($toks[0] as $tok) {
        if ($tok[0] === '(') {
            $out .= pdf_win1252_to_utf8(pdf_unescape(substr($tok, 1, -1)));
        } elseif ($tok[0] === '<') {
            $out .= pdf_hex_text(trim($tok, '<>'));
        } elseif ((float)$tok <= -120.0) {
            $out .= ' ';
        }
    }
    return $out;
}

/** Extrai texto dos operadores Tj/TJ/'/" dentro dos blocos BT...ET, com quebras de linha.
    Varre os operadores em ordem: quebras de linha do PDF viram \n (questões não se grudam). */
function pdf_stream_text(string $st): string {
    if (!preg_match_all('/BT(.*?)ET/s', $st, $blocks)) return '';
    $pat = '/(?P<lit>\((?:\\\\.|[^\\\\()])*\))(?P<show>\s*(?:Tj|\'|"))'
        . '|(?P<hex><[0-9A-Fa-f\s]+>)\s*Tj'
        . '|(?P<arr>\[.*?\])\s*TJ'
        . '|(?P<td>-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?)\s*Td\b'
        . '|(?P<nl>(?<![A-Za-z0-9])(?:T\*|TD|Tm)(?![A-Za-z0-9]))'
        . '/s';
    $out = '';
    foreach ($blocks[1] as $b) {
        if (!preg_match_all($pat, $b, $ops, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) continue;
        foreach ($ops as $op) {
            if ($op['lit'] !== null) {
                $out .= pdf_win1252_to_utf8(pdf_unescape(substr($op['lit'], 1, -1))) . ' ';
                if (str_contains((string)$op['show'], "'")) $out .= "\n"; // ' = mostra e pula linha
            } elseif ($op['hex'] !== null) {
                $out .= pdf_hex_text(trim($op['hex'], '<>')) . ' ';
            } elseif ($op['arr'] !== null) {
                $out .= pdf_tj_text(substr($op['arr'], 1, -1)) . ' ';
            } elseif ($op['td'] !== null) {
                $nums = preg_split('/\s+/', trim($op['td'])) ?: [0, 0];
                $ty = isset($nums[1]) ? (float)$nums[1] : 0.0;
                $out .= (abs($ty) > 0.5 ? "\n" : ' ');
            } elseif ($op['nl'] !== null) {
                $out .= "\n";
            }
        }
        $out .= "\n";
    }
    return $out;
}

/**
 * Extrai todo o texto legível de um PDF.
 * @return string texto ('' se não for PDF textual/legível)
 */
function pdf_text(string $path, int $maxBytes = 20000000): string {
    if (!is_file($path)) return '';
    $size = @filesize($path);
    if ($size === false || $size > $maxBytes || $size < 100) return '';
    $data = @file_get_contents($path);
    if ($data === false || substr($data, 0, 5) !== '%PDF-') return '';
    if (stripos($data, '/Encrypt') !== false) return ''; // protegido por senha
    $out = '';
    $pos = 0;
    $len = strlen($data);
    while (preg_match('/(?<!end)stream(\r\n|\n|\r)/', $data, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $s = $m[0][1];
        $start = $s + strlen($m[0][0]);
        $from = max(0, $s - 3000);
        $seg = substr($data, $from, $s - $from);
        if (!preg_match('#>>\s*$#', $seg)) {
            $pos = $start; // 'stream' dentro de binário: ignora
            continue;
        }
        $tail = strrpos($seg, '<<');
        $dict = $tail !== false ? substr($seg, $tail) : '';
        if (preg_match('#/Length\s+(\d+)(?!\s+\d+\s+R)#', $dict, $lm) && $start + (int)$lm[1] <= $len) {
            $raw = substr($data, $start, (int)$lm[1]);
            $pos = $start + (int)$lm[1];
        } else {
            $end = strpos($data, 'endstream', $start);
            if ($end === false) break;
            $raw = rtrim(substr($data, $start, $end - $start), "\r\n");
            $pos = $end + 9;
        }
        $isFlate = stripos($dict, 'FlateDecode') !== false || preg_match('#/Fl(\s|/|>>|\[)#', $dict);
        $content = null;
        if ($isFlate) {
            $dec = @gzuncompress($raw);
            if ($dec === false) $dec = @gzinflate($raw);
            if ($dec !== false) $content = $dec;
        } elseif (stripos($dict, 'ASCII85Decode') === false && stripos($dict, 'LZWDecode') === false && stripos($dict, 'DCTDecode') === false) {
            $content = $raw; // stream sem compressão
        }
        if (is_string($content) && str_contains($content, 'BT')) {
            $t = pdf_stream_text($content);
            if (trim($t) !== '') $out .= $t . "\n";
        }
    }
    $out = str_replace("\r", "\n", $out);
    $out = preg_replace('/[ \t\x0B\f]+/', ' ', $out) ?? $out;
    $out = preg_replace('/ +$/m', '', $out) ?? $out;
    $out = preg_replace('/\n{3,}/', "\n\n", $out) ?? $out;
    return trim($out);
}
