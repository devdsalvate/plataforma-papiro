<?php
declare(strict_types=1);
/* ============================================================
   PAPIRO MÁXIMO — Mídia do PDF (imagens embutidas + posições)
   Extrai figuras/gráficos embutidos no PDF e mapeia a posição
   de cada imagem e de cada "Questão N" por página, para vincular
   imagem -> questão automaticamente. Sem dependências (sem GD).
   ============================================================ */
require_once __DIR__ . '/pdf_extract.php';

/** Mapeia objetos "N 0 obj ... endobj" => [num => corpo].
 *  Também expande objetos guardados dentro de /ObjStm (fluxos de objetos comprimidos),
 *  usados por PDFs modernos com xref em stream (Acrobat, Canva, apps de scanner, etc.).
 *  Sem isso, Catalog/Pages/Page costumam ficar "invisíveis" para o parser: a árvore de
 *  páginas nunca é encontrada e NENHUMA figura ou página é vinculada às questões desses
 *  PDFs — mesmo que o texto (que usa outro caminho, sem depender da árvore) seja extraído
 *  normalmente. É a causa mais provável de "nunca anexa a página certa".
 */
function pdf_media_objects(string $data): array {
    $objs = [];
    if (!preg_match_all('/(\d+)\s+0\s+obj\b(.*?)\bendobj/s', $data, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $o) $objs[(int)$o[1]] = $o[2];
    foreach ($objs as $body) {
        if (!preg_match('#/Type\s*/ObjStm\b#', $body)) continue;
        $raw = pdf_media_stream_bytes($body);
        if ($raw === null) continue;
        if (stripos($body, 'FlateDecode') !== false) {
            $dec = @gzuncompress($raw);
            if ($dec === false) $dec = @gzinflate($raw);
            if ($dec === false) continue;
            $raw = $dec;
        }
        $n = preg_match('#/N\s+(\d+)#', $body, $mm) ? (int)$mm[1] : 0;
        $first = preg_match('#/First\s+(\d+)#', $body, $mm) ? (int)$mm[1] : 0;
        if ($n <= 0 || $first <= 0 || $first > strlen($raw)) continue;
        if (!preg_match_all('/(\d+)\s+(\d+)/', substr($raw, 0, $first), $pairs, PREG_SET_ORDER)) continue;
        $pairs = array_slice($pairs, 0, $n);
        foreach ($pairs as $i => $p) {
            $num = (int)$p[1];
            $off = (int)$p[2];
            $end = isset($pairs[$i + 1]) ? (int)$pairs[$i + 1][2] : (strlen($raw) - $first);
            if ($end <= $off || isset($objs[$num])) continue;
            $objs[$num] = substr($raw, $first + $off, $end - $off);
        }
    }
    return $objs;
}

/** Bytes entre stream/endstream de um corpo de objeto. */
function pdf_media_stream_bytes(string $body): ?string {
    if (!preg_match('/(?<!end)stream(\r\n|\n|\r)/', $body, $m, PREG_OFFSET_CAPTURE)) return null;
    $s = $m[0][1];
    $start = $s + strlen($m[0][0]);
    $seg = substr($body, 0, $s);
    if (!preg_match('#>>\s*$#', $seg)) return null;
    if (preg_match('#/Length\s+(\d+)(?!\s+\d+\s+R)#', $seg, $lm) && $start + (int)$lm[1] <= strlen($body)) {
        return substr($body, $start, (int)$lm[1]);
    }
    $end = strpos($body, 'endstream', $start);
    if ($end === false) return null;
    return rtrim(substr($body, $start, $end - $start), "\r\n");
}

/** Páginas em ordem: retorna [objs, [num_pag1, num_pag2, ...]]. */
function pdf_media_pages(string $data): array {
    $objs = pdf_media_objects($data);
    $root = 0;
    foreach ($objs as $num => $body) {
        if (preg_match('#/Type\s*/Catalog\b#', $body) && preg_match('#/Pages\s+(\d+)\s+0\s+R#', $body, $mm)) {
            $root = (int)$mm[1];
            break;
        }
    }
    if (!$root) {
        foreach ($objs as $num => $body) {
            if (preg_match('#/Type\s*/Pages\b#', $body)) {
                $root = $num;
                break;
            }
        }
    }
    if (!$root) return [$objs, []];
    $pages = [];
    $walk = function ($node) use (&$walk, $objs, &$pages) {
        if (!isset($objs[$node])) return;
        $body = $objs[$node];
        if (preg_match('#/Type\s*/Pages\b#', $body)) {
            if (preg_match('#/Kids\s*\[(.*?)\]#s', $body, $km)) {
                preg_match_all('#(\d+)\s+0\s+R#', $km[1], $kids);
                foreach ($kids[1] as $k) $walk((int)$k);
            }
        } elseif (preg_match('#/Type\s*/Page\b#', $body) || str_contains($body, '/MediaBox') || str_contains($body, '/Contents')) {
            $pages[] = $node;
        }
    };
    $walk($root);
    return [$objs, $pages];
}

/** Info da página: dimensões, contents e mapa XObject nome->obj. */
function pdf_media_page_info(array $objs, int $pnum): array {
    $body = $objs[$pnum] ?? '';
    $w = 595.0;
    $h = 842.0;
    if (preg_match('#/MediaBox\s*\[\s*[\d.\-]+\s+[\d.\-]+\s+([\d.\-]+)\s+([\d.\-]+)\s*\]#', $body, $mm)) {
        $w = (float)$mm[1];
        $h = (float)$mm[2];
    }
    $contents = [];
    if (preg_match('#/Contents\s*\[(.*?)\]#s', $body, $cm)) {
        preg_match_all('#(\d+)\s+0\s+R#', $cm[1], $refs);
        $contents = array_map('intval', $refs[1]);
    } elseif (preg_match('#/Contents\s+(\d+)\s+0\s+R#', $body, $cm)) {
        $contents = [(int)$cm[1]];
    }
    $xmap = [];
    $rpos = strpos($body, '/XObject');
    if ($rpos !== false) {
        preg_match_all('#/([A-Za-z0-9_.\-]+)\s+(\d+)\s+0\s+R#', substr($body, $rpos, 2000), $pairs, PREG_SET_ORDER);
        foreach ($pairs as $p) $xmap[$p[1]] = (int)$p[2];
    }
    return ['w' => $w, 'h' => $h, 'contents' => $contents, 'xmap' => $xmap];
}

/** Concatena e descomprime os content streams da página. */
function pdf_media_content_text(array $objs, array $refs): string {
    $out = '';
    foreach ($refs as $r) {
        if (!isset($objs[$r])) continue;
        $raw = pdf_media_stream_bytes($objs[$r]);
        if ($raw === null) continue;
        if (stripos($objs[$r], 'FlateDecode') !== false) {
            $dec = @gzuncompress($raw);
            if ($dec === false) $dec = @gzinflate($raw);
            if ($dec !== false) $raw = $dec;
            else continue;
        }
        $out .= $raw . "\n";
    }
    return $out;
}

/** Desfaz preditores PNG (filter bytes por linha). */
function pdf_media_unpredict(string $data, int $w, int $ch): ?string {
    $rowLen = $w * $ch;
    if ($rowLen <= 0) return null;
    $stride = $rowLen + 1;
    $rows = intdiv(strlen($data), $stride);
    if ($rows <= 0) return null;
    $out = '';
    $prev = str_repeat("\x00", $rowLen);
    for ($r = 0; $r < $rows; $r++) {
        $f = ord($data[$r * $stride]);
        $row = substr($data, $r * $stride + 1, $rowLen);
        if (strlen($row) < $rowLen) $row = str_pad($row, $rowLen, "\x00");
        $res = '';
        for ($i = 0; $i < $rowLen; $i++) {
            $a = $i >= $ch ? ord($res[$i - $ch]) : 0;
            $b = ord($prev[$i]);
            $c = $i >= $ch ? ord($prev[$i - $ch]) : 0;
            $v = ord($row[$i]);
            if ($f === 1) $v = ($v + $a) & 255;
            elseif ($f === 2) $v = ($v + $b) & 255;
            elseif ($f === 3) $v = ($v + (($a + $b) >> 1)) & 255;
            elseif ($f === 4) {
                $p = $a + $b - $c;
                $pa = abs($p - $a);
                $pb = abs($p - $b);
                $pc = abs($p - $c);
                $pr = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                $v = ($v + $pr) & 255;
            }
            $res .= chr($v);
        }
        $out .= $res;
        $prev = $res;
    }
    return $out;
}

/** Monta um PNG RGB/cinza 8-bit a partir de pixels crus. */
function pdf_media_make_png(int $w, int $h, int $ch, string $raw): ?string {
    $type = $ch === 1 ? 0 : 2;
    $rowLen = $w * $ch;
    if (strlen($raw) < $rowLen * $h) return null;
    $scan = '';
    for ($r = 0; $r < $h; $r++) $scan .= "\x00" . substr($raw, $r * $rowLen, $rowLen);
    $idat = gzcompress($scan);
    if ($idat === false) return null;
    $ihdr = pack('N', $w) . pack('N', $h) . chr(8) . chr($type) . "\x00\x00\x00";
    $chunk = function ($t, $d) { return pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d) & 0xffffffff); };
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IDAT', $idat) . $chunk('IEND', '');
}

/** Decodifica um XObject de imagem -> arquivo. Retorna info ou null. */
function pdf_media_decode_image(string $body, string $destDir, string $prefix): ?array {
    if (strpos($body, '/Image') === false) return null;
    $w = preg_match('#/Width\s+(\d+)#', $body, $m) ? (int)$m[1] : 0;
    $h = preg_match('#/Height\s+(\d+)#', $body, $m) ? (int)$m[1] : 0;
    $bpc = preg_match('#/BitsPerComponent\s+(\d+)#', $body, $m) ? (int)$m[1] : 8;
    if ($w <= 0 || $h <= 0 || $w > 3000 || $h > 3000 || $w * $h < 2500) return null; // ignora íconezinhos
    $raw = pdf_media_stream_bytes($body);
    if ($raw === null || $raw === '') return null;
    if (stripos($body, 'CCITTFaxDecode') !== false || stripos($body, 'JBIG2') !== false) return null;
    $isJpeg = stripos($body, 'DCTDecode') !== false;
    $isFlate = stripos($body, 'FlateDecode') !== false;
    if ($isJpeg && !$isFlate) {
        if (substr($raw, 0, 2) !== "\xFF\xD8") return null;
        $fn = $prefix . '.jpg';
        if (@file_put_contents($destDir . '/' . $fn, $raw) === false) return null;
        return ['arquivo' => $fn, 'w' => $w, 'h' => $h];
    }
    if ($isFlate) {
        $dec = @gzuncompress($raw);
        if ($dec === false) $dec = @gzinflate($raw);
        if ($dec === false) return null;
        $cs = 'rgb';
        if (preg_match('#/ColorSpace\s*/(DeviceGray|DeviceRGB|DeviceCMYK|CalGray|CalRGB)#', $body, $m)) {
            $cs = ($m[1] === 'DeviceGray' || $m[1] === 'CalGray') ? 'gray' : ($m[1] === 'DeviceCMYK' ? 'cmyk' : 'rgb');
        } elseif (preg_match('#/ColorSpace\s*\[\s*/(DeviceGray|DeviceRGB|DeviceCMYK|CalGray|CalRGB|Indexed|ICCBased)#', $body, $m)) {
            if ($m[1] === 'Indexed' || $m[1] === 'ICCBased') return null;
            $cs = str_contains($m[1], 'Gray') ? 'gray' : ($m[1] === 'DeviceCMYK' ? 'cmyk' : 'rgb');
        }
        if ($cs === 'cmyk' || $bpc !== 8) return null;
        $ch = $cs === 'gray' ? 1 : 3;
        $pred = preg_match('#/Predictor\s+(\d+)#', $body, $m) ? (int)$m[1] : 1;
        if ($pred > 1) {
            $dec = pdf_media_unpredict($dec, $w, $ch);
            if ($dec === null) return null;
        }
        $rowLen = $w * $ch;
        if (strlen($dec) < $rowLen * $h) return null;
        $png = pdf_media_make_png($w, $h, $ch, substr($dec, 0, $rowLen * $h));
        if ($png === null) return null;
        $fn = $prefix . '.png';
        if (@file_put_contents($destDir . '/' . $fn, $png) === false) return null;
        return ['arquivo' => $fn, 'w' => $w, 'h' => $h];
    }
    return null;
}

/** Posicionamentos de imagem no content: [['img'=>'Im1','y'=>f], ...]. */
function pdf_media_placements(string $content): array {
    $out = [];
    preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+cm|\/([A-Za-z0-9_.\-]+)\s+Do/', $content, $m, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
    $lastF = 0.0;
    foreach ($m as $t) {
        if ($t[7] !== null) $out[] = ['img' => $t[7], 'y' => $lastF];
        else $lastF = (float)$t[6];
    }
    return $out;
}

/** Segmentos de texto com posição Y: [['y'=>float,'t'=>texto], ...]. */
function pdf_media_text_segments(string $content): array {
    $segs = [];
    if (!preg_match_all('/BT(.*?)ET/s', $content, $blocks)) return [];
    foreach ($blocks[1] as $b) {
        $tx = 0.0;
        $ty = 0.0;
        $tl = 14.0;
        foreach (preg_split('/\r?\n/', $b) ?: [] as $line) {
            $events = [];
            if (preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Tm\b/', $line, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($mm as $x) $events[] = [$x[0][1], 'tm', [(float)$x[5][0], (float)$x[6][0]]];
            }
            if (preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+T[dD]\b/', $line, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($mm as $x) $events[] = [$x[0][1], 'td', [(float)$x[1][0], (float)$x[2][0]]];
            }
            if (preg_match_all('/(-?\d+(?:\.\d+)?)\s+TL\b/', $line, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($mm as $x) $events[] = [$x[0][1], 'tl', [(float)$x[1][0]]];
            }
            if (preg_match_all('/\bT\*/', $line, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as $x) $events[] = [$x[1], 'nl', []];
            }
            if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)\s*(?:Tj|"|\')|<([0-9A-Fa-f\s]+)>\s*Tj|\[(.*?)\]\s*TJ/', $line, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
                foreach ($mm as $x) $events[] = [$x[0][1], 'tx', $x];
            }
            usort($events, function ($a, $b) { return $a[0] <=> $b[0]; });
            foreach ($events as $ev) {
                [$off, $kind, $d] = $ev;
                if ($kind === 'tm') {
                    $tx = $d[0];
                    $ty = $d[1];
                } elseif ($kind === 'td') {
                    $tx += $d[0];
                    $ty += $d[1];
                } elseif ($kind === 'tl') {
                    $tl = $d[0];
                } elseif ($kind === 'nl') {
                    $ty -= $tl;
                } else {
                    $t = '';
                    if (is_array($d[1] ?? null)) $t = pdf_win1252_to_utf8(pdf_unescape($d[1][0]));
                    elseif (is_array($d[2] ?? null)) $t = pdf_hex_text($d[2][0]);
                    elseif (is_array($d[3] ?? null)) $t = pdf_tj_text($d[3][0]);
                    if ($t !== '') $segs[] = ['y' => $ty, 't' => $t];
                }
            }
        }
    }
    return $segs;
}

/**
 * Extrai imagens + marcadores de questão por página.
 * @return array ['paginas'=>[['h'=>,'imgs'=>[['arquivo','y_top','w','h']],'questoes'=>[['n','y_top']]]], 'total'=>int]
 */
function pdf_media_extract(string $path, string $destDir, string $prefix): array {
    $data = @file_get_contents($path);
    if ($data === false || substr($data, 0, 5) !== '%PDF-') return ['paginas' => [], 'total' => 0];
    [$objs, $pageNums] = pdf_media_pages($data);
    $result = [];
    $n = 0;
    foreach ($pageNums as $pi => $pnum) {
        $info = pdf_media_page_info($objs, $pnum);
        $content = pdf_media_content_text($objs, $info['contents']);
        $imgs = [];
        $used = [];
        foreach (pdf_media_placements($content) as $pl) {
            if (!isset($info['xmap'][$pl['img']])) continue;
            $onum = $info['xmap'][$pl['img']];
            if (isset($used[$onum]) || !isset($objs[$onum])) continue;
            $used[$onum] = 1;
            $n++;
            $dec = pdf_media_decode_image($objs[$onum], $destDir, $prefix . '-p' . ($pi + 1) . '-' . $n);
            if ($dec === null) continue;
            $imgs[] = ['arquivo' => $dec['arquivo'], 'y_top' => $info['h'] - $pl['y'], 'w' => $dec['w'], 'h' => $dec['h']];
        }
        $marks = [];
        $marksFracas = [];
        foreach (pdf_media_text_segments($content) as $sg) {
            // remove artefatos de acento sobreposto (fontes que desenham "~"/"^" soltos por
            // cima da letra em vez de usar o caractere acentuado): "Quest~ao13" -> "Questao13"
            $limpo = str_replace(['~', '^', '´', '`', '¨'], '', $sg['t']);
            if (preg_match('/quest[aã]o\s*0*(\d{1,3})/iu', $limpo, $m)) {
                $marks[] = ['n' => (int)$m[1], 'y_top' => $info['h'] - $sg['y']];
            } elseif (preg_match('/^\s*0*(\d{1,3})\s*[\)\.\-:–]\s*\S/u', $sg['t'], $m)) {
                // marcador fraco (só um número solto no início do trecho de texto): guarda à
                // parte — só vale a pena usar se a página não tiver NENHUM "Questão N" de verdade,
                // senão vira falso positivo pegando resposta/valor numérico do enunciado
                $marksFracas[] = ['n' => (int)$m[1], 'y_top' => $info['h'] - $sg['y']];
            }
        }
        if ($marks === []) $marks = $marksFracas;
        $seen = [];
        $u = [];
        foreach ($marks as $mk) {
            if (!isset($seen[$mk['n']])) {
                $seen[$mk['n']] = 1;
                $u[] = $mk;
            }
        }
        $result[] = ['h' => $info['h'], 'imgs' => $imgs, 'questoes' => $u];
    }
    $total = 0;
    foreach ($result as $pg) $total += count($pg['imgs']);
    return ['paginas' => $result, 'total' => $total];
}
