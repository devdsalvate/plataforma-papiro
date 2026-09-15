<?php
declare(strict_types=1);
/* ============================================================
   PAPIRO MÁXIMO — Importação de simulados PDF via IA (Groq)
   Fluxo: PDF -> texto -> IA identifica concurso/ano ->
   IA extrai questões no formato do banco -> rascunho p/ revisão.
   Imagens embutidas (gráficos/figuras) são extraídas e vinculadas.
   ============================================================ */
require_once __DIR__ . '/pdf_extract.php';
require_once __DIR__ . '/pdf_media.php';

/* Compat: se o config.php for antigo (sem as chaves novas), define vazias p/ não quebrar.
   Sem chave, o provedor é pulado sozinho — com só a Groq, funciona igual a antes. */
foreach (['GEMINI_API_KEY' => '', 'GEMINI_MODEL' => 'gemini-2.5-flash'] as $k => $v) {
    if (!defined($k)) define($k, $v);
}
if (!defined('GEMINI_FALLBACKS')) define('GEMINI_FALLBACKS', ['gemini-2.5-flash', 'gemini-2.0-flash']);

/** Migração leve (cria tabelas/colunas se não existirem). */
function import_migrate(): void {
    $pdo = db();
    $pk = DB_DRIVER === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $pdo->exec("CREATE TABLE IF NOT EXISTS importacoes (
      id $pk,
      arquivo VARCHAR(120) NOT NULL,
      concurso VARCHAR(20) NOT NULL DEFAULT 'Outro',
      ano INT NOT NULL DEFAULT 0,
      questoes INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS questao_imagens (
      id $pk,
      questao_id INT NULL,
      arquivo VARCHAR(120) NOT NULL,
      legenda VARCHAR(150) NOT NULL DEFAULT '',
      origem VARCHAR(120) NOT NULL DEFAULT '',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec("ALTER TABLE questoes ADD COLUMN origem VARCHAR(120) NOT NULL DEFAULT ''");
    } catch (Throwable $e) { /* coluna já existe */
    }
    try {
        $pdo->exec("ALTER TABLE questoes ADD COLUMN numero INT NOT NULL DEFAULT 0");
    } catch (Throwable $e) { /* coluna já existe */
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS import_paginas (
      id $pk,
      arquivo VARCHAR(120) NOT NULL,
      pagina INT NOT NULL DEFAULT 1,
      arquivo_img VARCHAR(120) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS import_jobs (
      id $pk,
      arquivo VARCHAR(120) NOT NULL,
      concurso VARCHAR(20) NOT NULL DEFAULT '',
      ano INT NOT NULL DEFAULT 0,
      inicio INT NOT NULL DEFAULT 0,
      total INT NOT NULL DEFAULT 0,
      esperadas INT NOT NULL DEFAULT 0,
      salvas INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'fila',
      erros TEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec("ALTER TABLE questoes ADD COLUMN pagina INT NOT NULL DEFAULT 0");
    } catch (Throwable $e) { /* coluna já existe */
    }
    try {
        $pdo->exec("ALTER TABLE questoes ADD COLUMN regiao VARCHAR(32) NOT NULL DEFAULT ''");
    } catch (Throwable $e) { /* coluna já existe */
    }
}

function import_simulados_dir(): string {
    $d = APP_ROOT . '/simulados';
    if (!is_dir($d)) mkdir($d, 0775, true);
    return $d;
}

function import_uploads_dir(): string {
    $d = APP_ROOT . '/assets/uploads/questoes';
    if (!is_dir($d)) mkdir($d, 0775, true);
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '<!-- protegido -->');
    return $d;
}

/** Upload manual de imagens p/ uma questão. Retorna [salvos, erros]. */
function questao_upload_imagens(int $qid, array $files, string $origem = ''): array {
    $saved = [];
    $erros = [];
    $norm = [];
    if (isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $i => $nm) {
            $norm[] = [
                'name' => $nm, 'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? 1, 'size' => $files['size'][$i] ?? 0,
            ];
        }
    }
    $extOk = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    foreach ($norm as $f) {
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) <= 0) continue;
        if ($f['size'] > 5 * 1024 * 1024) {
            $erros[] = (string)$f['name'] . ': máximo 5 MB';
            continue;
        }
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!isset($extOk[$ext])) {
            $erros[] = (string)$f['name'] . ': formato inválido (use JPG/PNG/GIF/WebP)';
            continue;
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || ($info['mime'] ?? '') !== $extOk[$ext]) {
            $erros[] = (string)$f['name'] . ': arquivo de imagem inválido';
            continue;
        }
        $fn = 'q' . $qid . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], import_uploads_dir() . '/' . $fn)) {
            $erros[] = (string)$f['name'] . ': falha ao salvar';
            continue;
        }
        db()->prepare('INSERT INTO questao_imagens (questao_id, arquivo, legenda, origem) VALUES (?,?,?,?)')->execute([$qid, $fn, '', $origem]);
        $saved[] = $fn;
    }
    return [$saved, $erros];
}

/** Exclui imagem (arquivo + registro). */
function questao_excluir_imagem(int $imgId): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT arquivo FROM questao_imagens WHERE id = ?');
    $st->execute([$imgId]);
    $arq = $st->fetchColumn();
    if ($arq) @unlink(import_uploads_dir() . '/' . basename((string)$arq));
    $pdo->prepare('DELETE FROM questao_imagens WHERE id = ?')->execute([$imgId]);
}

/** Marcador de início de questão ("QUESTÃO 12", "Questao 3", "Q. 5", ...). */
const IMPORT_QMARK = '/^\s*(?:QUEST[AÃ]O|QUEST\.?|Q\.)\s*\d{1,3}\b/iu';

/** Quebra o texto em trechos (~N chars, máx. 8 questões cada) p/ caber na IA.
    Tenta não cortar no meio da questão: prefere quebrar onde começa outra questão.
    Trechos maiores = menos chamadas à IA = processamento bem mais rápido (cada chamada
    paga um intervalo mínimo fixo por causa do limite de requisições/min do provedor —
    ver groq_medidor — então menos chamadas quase sempre vale mais que chamadas menores). */
function import_chunk_text(string $text, int $max = 4500): array {
    $lines = preg_split('/\n+/', trim($text)) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), function ($l) { return $l !== ''; }));
    // quebra linhas longas onde uma nova questão começa no meio (texto "grudado")
    $flat = [];
    foreach ($lines as $ln) {
        $parts = preg_split('/(?<=\s)(?=(?:QUEST[AÃ]O|QUEST\.?|Q\.)\s*\d{1,3}\b)/iu', $ln) ?: [$ln];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') $flat[] = $p;
        }
    }
    $lines = $flat;
    // agrupa linhas em blocos por questão (marcadores típicos: "QUESTÃO 12", "Questao 3 -", ...)
    $blocks = [];
    $cur = [];
    $hasMark = false;
    foreach ($lines as $ln) {
        $isStart = (bool)preg_match(IMPORT_QMARK, $ln)
            || (bool)preg_match('/^\s*\d{1,3}\s*[).\-:]\s*\S/u', $ln);
        if ($isStart && $cur !== []) {
            $blocks[] = implode("\n", $cur);
            $cur = [];
            $hasMark = true;
        }
        $cur[] = $ln;
    }
    if ($cur !== []) $blocks[] = implode("\n", $cur);
    // sem marcadores: comportamento antigo (corte por tamanho)
    if (!$hasMark) $blocks = $lines;
    // empacota blocos em trechos de até $max chars (bloco gigante é fatiado)
    $chunks = [];
    $cur = '';
    $curQ = 0;
    $push = function (string $piece) use (&$chunks, &$cur, &$curQ, $max) {
        $pq = max(1, import_contar_esperadas($piece));
        if ($cur !== '' && (strlen($cur) + 1 + strlen($piece) > $max || $curQ + $pq > 8)) {
            $chunks[] = $cur;
            $cur = '';
            $curQ = 0;
        }
        $cur .= ($cur === '' ? '' : "\n") . $piece;
        $curQ += $pq;
    };
    foreach ($blocks as $b) {
        if (strlen($b) > $max) {
            if ($cur !== '') {
                $chunks[] = $cur;
                $cur = '';
                $curQ = 0;
            }
            foreach (mb_str_split($b, $max) as $fat) $chunks[] = $fat;
        } else {
            $push($b);
        }
    }
    if ($cur !== '') $chunks[] = $cur;
    return $chunks;
}

/** POST na IA: Gemini (modelos 'gemini-*') ou Groq (demais). Formato OpenAI nos dois.
    Retorna [http_code, body_array|null, curl_err, retry_after_seg|null]. */
function llm_post(array $body, string $model): array {
    if (str_starts_with($model, 'gemini')) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
        $key = GEMINI_API_KEY;
    } else {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $key = GROQ_API_KEY;
    }
    $payload = json_encode($body);
    if (function_exists('curl_init')) {
        $retry = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADERFUNCTION => function ($ch2, $h) use (&$retry) {
                if (preg_match('/^retry-after:\s*(\d+)/i', $h, $m)) $retry = (int)$m[1];
                return strlen($h);
            },
        ]);
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = (string)curl_error($ch);
        curl_close($ch);
        if ($out === false) return [0, null, $cerr, null];
        return [$code, json_decode((string)$out, true), '', $retry];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'content' => $payload, 'timeout' => 120, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $key,
    ]]);
    $out = @file_get_contents($url, false, $ctx);
    if ($out === false) return [0, null, 'sem curl', null];
    $code = 200;
    $retry = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        if (preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $m)) $code = (int)$m[1];
        foreach ($http_response_header as $h) {
            if (preg_match('/^retry-after:\s*(\d+)/i', (string)$h, $m2)) $retry = (int)$m2[1];
        }
    }
    return [$code, json_decode((string)$out, true), '', $retry];
}

/** [substitída por llm_post] POST só na Groq. Mantida p/ compatibilidade. */
function groq_post(array $body): array {
    $payload = json_encode($body);
    if (function_exists('curl_init')) {
        $retry = null;
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADERFUNCTION => function ($ch2, $h) use (&$retry) {
                if (preg_match('/^retry-after:\s*(\d+)/i', $h, $m)) $retry = (int)$m[1];
                return strlen($h);
            },
        ]);
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = (string)curl_error($ch);
        curl_close($ch);
        if ($out === false) return [0, null, $cerr, null];
        return [$code, json_decode((string)$out, true), '', $retry];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'content' => $payload, 'timeout' => 120, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . GROQ_API_KEY,
    ]]);
    $out = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);
    if ($out === false) return [0, null, 'sem curl', null];
    $code = 200;
    $retry = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        if (preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $m)) $code = (int)$m[1];
        foreach ($http_response_header as $h) {
            if (preg_match('/^retry-after:\s*(\d+)/i', (string)$h, $m2)) $retry = (int)$m2[1];
        }
    }
    return [$code, json_decode((string)$out, true), '', $retry];
}

/** Tokens/minuto (free tier) por modelo — p/ dosar as chamadas e não tomar 429. */
function groq_tpm(string $model): int {
    if (str_starts_with($model, 'gemini')) return 250000; // Gemini grátis: cota gigante (~250k-1M)
    if (str_starts_with($model, 'groq/compound')) return 70000;
    if (str_starts_with($model, 'qwen/')) return 6000;
    return 8000; // gpt-oss-120b/20b e demais
}

/** Medidor de ritmo da Groq (janela SEPARADA por modelo: cada modelo tem sua cota).
    'esperar' = dorme o necessário antes de chamar; 'ajustar' = corrige p/ uso real. */
function groq_medidor(string $op, string $model = '', int $tokens = 0): void {
    static $uso = []; // modelo => [[tempo, tokens], ...] dos últimos 60s
    static $last = []; // modelo => tempo da última chamada
    $m = $model !== '' ? $model : '_';
    if (!isset($uso[$m])) $uso[$m] = [];
    if ($op === 'ajustar') {
        // ajusta o registro mais recente (a chamada que acabou de voltar — PHP é sequencial)
        $todas = [];
        foreach ($uso as $mm => $lst) {
            if ($lst !== []) $todas[$mm] = $lst[count($lst) - 1][0];
        }
        if ($todas === []) return;
        arsort($todas);
        $mm = (string)key($todas);
        $uso[$mm][count($uso[$mm]) - 1][1] = max(100, $tokens);
        return;
    }
    $limite = (int)(groq_tpm($model) * 0.85);
    $now = microtime(true);
    $gapMin = str_starts_with($model, 'gemini') ? 4.5 : 2.2; // Gemini ~10-15 RPM; Groq 30 RPM
    $gap = $gapMin - ($now - ($last[$m] ?? 0.0));
    if ($gap > 0) {
        usleep((int)($gap * 1000000));
        $now = microtime(true);
    }
    $podar = function () use (&$uso, $m, &$now) {
        $now = microtime(true);
        $uso[$m] = array_values(array_filter($uso[$m], function ($e) use ($now) { return $now - $e[0] < 60; }));
        $soma = 0;
        foreach ($uso[$m] as $e) $soma += $e[1];
        return $soma;
    };
    $soma = $podar();
    while ($soma + $tokens > $limite && $uso[$m] !== []) {
        $espera = (int)(61 - ($now - $uso[$m][0][0]));
        sleep(min(max($espera, 2), 65));
        $soma = $podar();
    }
    $uso[$m][] = [$now, $tokens];
    $last[$m] = $now;
}

/** Erro é "modelo inexistente/descontinuado"? (vale tentar o próximo) */
function groq_is_model_error(int $code, $body): bool {
    if ($code === 404) return true;
    $msg = strtolower((string)(is_array($body) ? ($body['error']['message'] ?? '') : ''));
    return $msg !== '' && (str_contains($msg, 'does not exist') || str_contains($msg, 'decommissioned') || str_contains($msg, 'not found') || str_contains($msg, 'model_not_found'));
}

/** Tenta salvar questões de um JSON cortado (resposta estourou o limite).
    Varre os objetos {...} completos do array "questoes" e decodifica um a um. */
function json_reparar_questoes(string $raw): ?array {
    $p = strpos($raw, '"questoes"');
    if ($p === false) return null;
    $p = strpos($raw, '[', $p);
    if ($p === false) return null;
    $out = [];
    $len = strlen($raw);
    $i = $p + 1;
    while ($i < $len) {
        $c = $raw[$i];
        if ($c !== '{') {
            $i++;
            continue;
        }
        // extrai objeto com chaves balanceadas (respeitando strings com escape)
        $depth = 0;
        $inStr = false;
        $esc = false;
        $start = $i;
        for (; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($inStr) {
                if ($esc) $esc = false;
                elseif ($ch === '\\') $esc = true;
                elseif ($ch === '"') $inStr = false;
            } elseif ($ch === '"') {
                $inStr = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $i++;
                    break;
                }
            }
        }
        if ($depth !== 0) break; // objeto incompleto (corte): ignora o resto
        $obj = json_decode(substr($raw, $start, $i - $start), true);
        if (is_array($obj) && isset($obj['enunciado'])) $out[] = $obj;
    }
    return $out !== [] ? ['questoes' => $out] : null;
}

/** Último modelo que respondeu (p/ mostrar "via Gemini/Groq" no progresso). */
function llm_ultimo_modelo(?string $set = null): string {
    static $m = '';
    if ($set !== null) $m = $set;
    return $m;
}

/** Chama a IA exigindo JSON: Gemini 1º, Groq de reserva. Dosa o ritmo, retenta 429/5xx e troca de modelo/provedor se preciso. */
function groq_json(array $messages, int $maxTokens = 3000): array {
    // ordem: Gemini 1º (cota gigante) → Mistral → Groq; só entra quem tem chave
    $models = [];
    if (GEMINI_API_KEY !== '') foreach (array_merge([GEMINI_MODEL], GEMINI_FALLBACKS) as $mm) $models[] = $mm;
    if (GROQ_API_KEY !== '') foreach (array_merge([GROQ_MODEL], GROQ_FALLBACKS) as $mm) $models[] = $mm;
    $models = array_values(array_unique($models));
    if ($models === []) return [null, 'Sem chave de IA (configure GEMINI_API_KEY no install.php ou config.local.php — grátis em aistudio.google.com/apikey).'];
    $inLen = 0;
    foreach ($messages as $mm) $inLen += strlen((string)($mm['content'] ?? ''));
    $est = (int)($inLen / 4) + $maxTokens; // estimativa conservadora de tokens
    $lastErr = 'Falha na IA.';
    $dormido = 0; // orçamento total de espera desta chamada (evita travar a requisição)
    foreach ($models as $model) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];
        groq_medidor('esperar', $model, $est);
        llm_ultimo_modelo($model);
        [$code, $body, $cerr, $retry] = llm_post($payload, $model);
        // 429 (cota), 5xx ou rede: espera e retenta o MESMO modelo (máx. 2x, orçamento global 150s)
        $tent = 0;
        while (($code === 429 || ($code >= 500 && $code < 600) || $code === 0) && $tent < 2) {
            $tent++;
            $base = ($code === 429) ? 15 * $tent : 8 * $tent;
            $wait = (int)min(max($retry ?? 0, $base), 60);
            if ($dormido + $wait > 150) break; // estourou o orçamento: tenta o próximo modelo
            sleep($wait);
            $dormido += $wait;
            groq_medidor('esperar', $model, $est);
            llm_ultimo_modelo($model);
            [$code, $body, $cerr, $retry] = llm_post($payload, $model);
        }
        if ($code === 0) {
            $lastErr = 'Falha de rede (' . $cerr . ').';
            continue; // tenta o próximo modelo
        }
        if ($code >= 200 && $code < 300) {
            $tot = is_array($body) ? (int)($body['usage']['total_tokens'] ?? 0) : 0;
            if ($tot > 0) groq_medidor('ajustar', '', $tot); // troca estimativa pelo uso real
            $content = is_array($body) ? ($body['choices'][0]['message']['content'] ?? '') : '';
            $data = json_decode((string)$content, true);
            if (!is_array($data)) {
                $rep = json_reparar_questoes((string)$content); // resposta cortada? salva as completas
                if ($rep !== null) return [$rep, null];
                return [null, 'A IA retornou JSON inválido.'];
            }
            return [$data, null];
        }
        $prov = str_starts_with($model, 'gemini') ? 'Gemini' : 'Groq';
        if (is_array($body) && isset($body[0]['error'])) $body = $body[0]; // Gemini embrulha o erro em [{...}]
        $msg = is_array($body) ? ((string)($body['error']['message'] ?? $body['detail'] ?? $body['message'] ?? ('HTTP ' . $code))) : ('HTTP ' . $code); // Mistral usa {"detail":...}
        $lastErr = $prov . ': ' . $msg;
        // 400/401/403 (chave ruim, gaguejou no JSON...), 429/5xx ou modelo inexistente -> tenta o próximo; outro erro -> desiste
        if ($code === 400 || $code === 401 || $code === 403 || $code === 429 || ($code >= 500 && $code < 600) || groq_is_model_error($code, $body)) continue;
        return [null, $lastErr];
    }
    return [null, $lastErr];
}

/** Identifica concurso e ano pelo início do texto. */
function import_detect(string $sample): array {
    [$data, $err] = groq_json([
        ['role' => 'system', 'content' => 'Você identifica simulados militares brasileiros. Responda SOMENTE JSON: {"concurso":"EFOMM|EPCAR|EEAR|CN|ITA|ESA|EsPCEx|AFA|IME|EEAM|CFN|Outro","ano":2024}. "CN" = Colégio Naval. Se não souber o concurso use "Outro"; se não souber o ano use 0.'],
        ['role' => 'user', 'content' => mb_substr($sample, 0, 3000)],
    ], 400);
    if ($err !== null) return ['concurso' => 'Outro', 'ano' => 0, 'erro' => $err];
    $c = strtoupper(trim((string)($data['concurso'] ?? 'Outro')));
    if (str_contains($c, 'NAVAL')) $c = 'CN';
    if (!in_array($c, CONCURSOS_TODOS, true)) $c = 'Outro';
    return ['concurso' => $c, 'ano' => (int)($data['ano'] ?? 0)];
}

/** Extrai as questões de um trecho. Retorna [itens, erro|null]. */
function import_extract_chunk(string $chunk, string $concurso, int $ano): array {
    $sys = 'Você extrai questões de simulados militares para um banco de questões. Responda SOMENTE JSON: {"questoes":[{"numero":1,"materia":"Matemática|Física|Química|Português|Inglês|Ciências|Geral","assunto":"tema curto","dificuldade":"Fácil|Médio|Difícil","enunciado":"somente a pergunta/comando, SEM as alternativas","alternativas":["alt A","alt B","alt C","alt D","alt E"],"gabarito":0,"resolucao":"explicação curta"}]}. Regras: extraia TODAS as questões completas do trecho, do início ao fim, sem parar nas primeiras e sem pular nenhuma; numero = número da questão no PDF (0 se não souber); gabarito é o ÍNDICE da correta (0=A,1=B,2=C,3=D,4=E); use o gabarito oficial do texto quando houver; sem gabarito, resolva e deduza a correta; crie resolução curta (2-4 linhas) quando não houver; ignore questões incompletas/cortadas; nunca invente questões; transcreva enunciados e alternativas NA ÍNTEGRA, sem resumir, sem cortar versos/citações e sem "etc"; enunciado limpo sem "Questão N" e SEM repetir as alternativas (elas vão SÓ no campo alternativas, pois já aparecem clicáveis na tela).';
    [$data, $err] = groq_json([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => "Concurso: $concurso. Ano: " . ($ano > 0 ? $ano : 'desconhecido') . ".\n\nTEXTO:\n" . $chunk],
    ], 6000);
    if ($err !== null) return [[], $err];
    $items = $data['questoes'] ?? [];
    return [is_array($items) ? array_values($items) : [], null];
}

/** Remove bloco de alternativas (A–E) do enunciado, caso a IA o tenha repetido ali.
    As alternativas moram em alt_a..alt_e e já aparecem clicáveis no resolver.
    Só corta quando encontra a sequência A→B→C→D em ordem (evita falsos positivos). */
function import_limpar_enunciado(string $en): string {
    $en = trim($en);
    if ($en === '') return $en;
    // marcadores: A) B. (C) [D] A- A: ... com ou sem ** ; não pode estar grudado em palavra/número (det(2A), dada:, ...)
    if (!preg_match_all('/(?<![A-Za-z0-9])\*{0,2}[\(\[]?([A-Ea-e])\*{0,2}[\)\.\-:]\s*(?=\S)/u', $en, $m, PREG_OFFSET_CAPTURE)) return $en;
    $marks = [];
    foreach ($m[1] as $i => $ml) $marks[] = ['letra' => strtoupper($ml[0]), 'pos' => (int)$m[0][$i][1]];
    $n = count($marks);
    for ($i = 0; $i < $n; $i++) {
        if ($marks[$i]['letra'] !== 'A') continue;
        $prev = 0;
        $want = 1; // espera B, depois C, depois D
        for ($j = $i + 1; $j < $n && $want <= 3; $j++) {
            $cur = ord($marks[$j]['letra']) - 65;
            if ($cur === $prev) continue; // letra repetida dentro da opção, ignora
            if ($cur === $want) {
                $prev = $want;
                $want++;
                continue;
            }
            break; // fora de ordem: não é bloco de alternativas
        }
        if ($want > 3) {
            $novo = trim(substr($en, 0, $marks[$i]['pos']));
            if (mb_strlen($novo) >= 10) return $novo;
        }
    }
    return $en;
}

/** Valida e salva itens como RASCUNHO (ativo=0). Retorna [ids, puladas, motivos]. */
function import_save_items(array $items, string $arquivo, string $concurso, int $ano, int $by): array {
    $pdo = db();
    $ids = [];
    $puladas = 0;
    $motivos = [];
    $st = $pdo->prepare('INSERT INTO questoes (slug,concurso,ano,numero,materia,assunto,dificuldade,enunciado,alt_a,alt_b,alt_c,alt_d,alt_e,gabarito,resolucao,ativo,origem,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
        $rot = (is_array($it) && (int)($it['numero'] ?? 0) > 0) ? ('Q' . (int)$it['numero']) : 'item';
        if (!is_array($it)) {
            $puladas++;
            if (count($motivos) < 12) $motivos[] = "$rot: formato inválido";
            continue;
        }
        $en = import_limpar_enunciado(trim((string)($it['enunciado'] ?? '')));
        $alts = $it['alternativas'] ?? null;
        $gab = (int)($it['gabarito'] ?? -1);
        $motivo = '';
        if (mb_strlen($en) < 10) $motivo = 'enunciado curto/incompleto';
        elseif (!is_array($alts) || count($alts) !== 5) $motivo = 'alternativas != 5 (veio ' . (is_array($alts) ? count($alts) : 'nenhuma') . ')';
        elseif ($gab < 0 || $gab > 4) $motivo = 'gabarito inválido';
        if ($motivo !== '') {
            $puladas++;
            if (count($motivos) < 12) $motivos[] = "$rot: $motivo";
            continue;
        }
        $alts = array_values(array_map(function ($a) { return trim((string)$a); }, $alts));
        if ($alts[0] === '' || $alts[1] === '' || $alts[2] === '' || $alts[3] === '' || $alts[4] === '') {
            $puladas++;
            if (count($motivos) < 12) $motivos[] = "$rot: alternativa vazia";
            continue;
        }
        $num = max(0, min(999, (int)($it['numero'] ?? 0)));
        $mat = trim((string)($it['materia'] ?? 'Geral'));
        if (!in_array($mat, MATERIAS, true)) $mat = 'Geral';
        $dif = trim((string)($it['dificuldade'] ?? 'Médio'));
        if (!in_array($dif, DIFICULDADES, true)) $dif = 'Médio';
        $ass = mb_substr(trim((string)($it['assunto'] ?? '')), 0, 100);
        $res = trim((string)($it['resolucao'] ?? ''));
        if ($res === '') $res = 'Gabarito: letra ' . LETRAS[$gab] . '.';
        do {
            $slug = 'pdf-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $chk = $pdo->prepare('SELECT id FROM questoes WHERE slug = ?');
            $chk->execute([$slug]);
        } while ($chk->fetch());
        try {
            $st->execute([$slug, $concurso, $ano > 0 ? $ano : (int)date('Y'), $num, $mat, $ass, $dif, $en, $alts[0], $alts[1], $alts[2], $alts[3], $alts[4], $gab, $res, 0, $arquivo, $by]);
            $ids[] = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            $puladas++;
            if (count($motivos) < 12) $motivos[] = "$rot: erro ao salvar";
        }
    }
    return [$ids, $puladas, $motivos];
}

/** Conta quantas questões o texto parece ter (p/ conferir se a IA pegou tudo). 0 = não deu p/ saber. */
function import_contar_esperadas(string $texto): int {
    $n = preg_match_all('/^\s*(?:QUEST[AÃ]O|QUEST\.?|Q\.)\s*\d{1,3}\b/miu', $texto);
    return is_int($n) ? $n : 0;
}

/** Estima a página das questões que ficaram sem página (pela posição do texto no PDF).
    Marca regiao='~' (aproximada): o print abre na página estimada; sem recorte. */
function import_estimar_paginas(string $arquivo, string $texto, int $npages): int {
    if ($npages < 1 || strlen($texto) < 100) return 0;
    $pdo = db();
    $st = $pdo->prepare('SELECT id, enunciado FROM questoes WHERE origem = ? AND pagina <= 0');
    $st->execute([$arquivo]);
    $rows = $st->fetchAll();
    if ($rows === []) return 0;
    $up = $pdo->prepare("UPDATE questoes SET pagina = ?, regiao = '~' WHERE id = ?");
    // normaliza p/ comparar (a IA muda aspas/maiúsculas): minúsculas + aspas retas
    $norm = function ($s) { $s = (string)preg_replace('/\s+/', ' ', (string)$s); $s = mb_strtolower($s); $s = str_replace(['‘', '’', '“', '”', '«', '»', '´', '`'], ["'", "'", '"', '"', '"', '"', "'", "'"], $s); return trim(trim($s), "\"'"); };
    $texto = $norm($texto);
    $len = strlen($texto);
    $n = 0;
    foreach ($rows as $r) {
        $txt = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$r['enunciado'])));
        $pos = false;
        $txt = $norm($txt);
        foreach ([80, 50, 30] as $tam) {
            if (mb_strlen($txt) < $tam) continue;
            $pos = mb_strpos($texto, mb_substr($txt, 0, $tam));
            if ($pos !== false) break;
        }
        if ($pos === false) continue;
        $pg = (int)floor($pos / max(1, $len) * $npages) + 1;
        $up->execute([max(1, min($npages, $pg)), (int)$r['id']]);
        $n++;
    }
    return $n;
}

/** Re-mapeia páginas/regiões das questões de um arquivo SEM gastar IA (usa o PDF local).
    Retorna ['ok'=>, 'pagina'=>n, 'regiao'=>n, 'estimada'=>n]. */
function import_remapear(string $path, string $arquivo): array {
    $arquivo = basename($arquivo);
    if (!is_file($path)) return ['ok' => false, 'erro' => 'PDF não encontrado.'];
    try {
        $prefix = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($arquivo, PATHINFO_FILENAME));
        $prefix = strtolower(trim((string)$prefix, '-')) . '-' . substr(md5($arquivo), 0, 6);
        if ($prefix === '-') $prefix = 'pdf';
        $pm = pdf_media_extract($path, import_uploads_dir(), $prefix);
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM questoes WHERE origem = ?');
        $st->execute([$arquivo]);
        import_link_images($pm, $arquivo, $st->fetchAll(PDO::FETCH_COLUMN));
        $texto = pdf_text($path);
        $np = count($pm['paginas']);
        if ($np > 0 && strlen($texto) >= 100) import_estimar_paginas($arquivo, $texto, $np);
        $dg = $pdo->query('SELECT COUNT(*) t FROM questoes WHERE origem = ' . $pdo->quote($arquivo))->fetch();
        if ((int)($dg['t'] ?? 0) === 0) return ['ok' => false, 'erro' => 'Nenhuma questão deste arquivo no banco.'];
        $c = $pdo->query('SELECT COALESCE(SUM(pagina > 0),0) pg, COALESCE(SUM(regiao <> ' . "''" . ' AND regiao <> ' . "'~'" . '),0) rg, COALESCE(SUM(regiao = ' . "'~'" . '),0) est FROM questoes WHERE origem = ' . $pdo->quote($arquivo))->fetch();
        return ['ok' => true, 'pagina' => (int)($c['pg'] ?? 0), 'regiao' => (int)($c['rg'] ?? 0), 'estimada' => (int)($c['est'] ?? 0)];
    } catch (Throwable $e) {
        return ['ok' => false, 'erro' => $e->getMessage()];
    }
}

/* ---------------- Trabalhos em 2º plano (jobs) ---------------- */

/** Cria um trabalho de importação (trecho a trecho, retomável). Retorna id ou 0. */
function job_criar(string $arquivo, int $by, array $opts = []): int {
    $arquivo = basename($arquivo);
    $texto = pdf_text(import_simulados_dir() . '/' . $arquivo);
    if (strlen($texto) < 100) return 0;
    $chunks = import_chunk_text($texto);
    if ($chunks === []) return 0;
    $pdo = db();
    $pdo->prepare("DELETE FROM import_jobs WHERE arquivo = ? AND status <> 'concluido'")->execute([$arquivo]);
    $st = $pdo->prepare('INSERT INTO import_jobs (arquivo,concurso,ano,inicio,total,esperadas,salvas,status) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$arquivo, (string)($opts['concurso'] ?? ''), (int)($opts['ano'] ?? 0), 0, count($chunks), import_contar_esperadas($texto), 0, 'fila']);
    return (int)$pdo->lastInsertId();
}

function job_get(int $id): ?array {
    $st = db()->prepare('SELECT * FROM import_jobs WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Executa UM trecho do trabalho (retomável; 1 trecho por chamada p/ nunca estourar tempo). */
function job_step(array $job, int $by): array {
    @ignore_user_abort(true); // se o navegador/proxy desistir da resposta, o passo termina e salva mesmo assim
    $pdo = db();
    $id = (int)$job['id'];
    $pdo->prepare("UPDATE import_jobs SET status='rodando', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    $path = import_simulados_dir() . '/' . basename((string)$job['arquivo']);
    $opts = ['inicio' => (int)$job['inicio'], 'max_chunks' => 1];
    if ((string)$job['concurso'] !== '') $opts['concurso'] = (string)$job['concurso'];
    if ((int)$job['ano'] > 0) $opts['ano'] = (int)$job['ano'];
    try {
        $res = import_process($path, $by, $opts);
    } catch (Throwable $e) {
        $res = ['ok' => false, 'erro' => $e->getMessage()];
    }
    if (empty($res['ok'])) {
        $erros = trim((string)$job['erros'] . "\ntrecho " . ((int)$job['inicio'] + 1) . ': ' . $res['erro']);
        $pdo->prepare("UPDATE import_jobs SET status='pausado', erros=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$erros, $id]);
        return ['ok' => false, 'erro' => (string)$res['erro']];
    }
    if ((int)$job['inicio'] === 0) {
        $pdo->prepare('UPDATE import_jobs SET concurso=?, ano=? WHERE id=?')->execute([$res['concurso'], $res['ano'], $id]);
    }
    $done = ((int)$res['falta'] <= 0);
    $erros = (string)$job['erros'];
    foreach (($res['erros'] ?? []) as $e) $erros .= ($erros === '' ? '' : "\n") . $e;
    $pdo->prepare('UPDATE import_jobs SET inicio=?, salvas=?, status=?, erros=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
        ->execute([(int)$res['proximo'], (int)$res['total_salvas'], $done ? 'concluido' : 'pausado', $erros, $id]);
    return ['ok' => true, 'res' => $res, 'done' => $done];
}

/** Monta a resposta JSON do passo (textos já escapados p/ o JS injetar). */
function job_api_state(array $job, bool $done, array $res = []): array {
    $feitos = (int)$job['inicio'];
    $total = max(1, (int)$job['total']);
    $salvas = (int)$job['salvas'];
    $esperadas = (int)$job['esperadas'];
    $m = 'Trecho ' . $feitos . '/' . $total . ' · salvas: <b>' . $salvas . '</b>';
    if ($esperadas > 0) $m .= ' (esperadas ~' . $esperadas . ')';
    if ($res !== []) {
        $m .= '<br>Última leva: +' . count($res['ids'] ?? []) . ' questões';
        $via = llm_ultimo_modelo();
        if ($via !== '') {
            $rot = str_starts_with($via, 'gemini') ? 'Gemini' : (str_contains($via, '120b') ? 'Groq 120b' : (str_contains($via, '20b') ? 'Groq 20b' : $via));
            $m .= ' <span class="muted">(via ' . e($rot) . ')</span>';
        }
        if (!empty($res['erros'])) $m .= ' · ⚠️ ' . e(implode(' | ', array_slice($res['erros'], 0, 2)));
    }
    $out = ['ok' => true, 'done' => $done, 'feitos' => $feitos, 'total' => $total, 'salvas' => $salvas, 'esperadas' => $esperadas, 'msgHtml' => $m, 'doneHtml' => ''];
    if ($done) {
        $a = e((string)$job['arquivo']);
        $h = '<p>✅ <b>' . $salvas . ' questões</b> importadas de <code>' . $a . '</code>';
        if ((string)$job['concurso'] !== '') {
            $h .= ' (' . concurso_icone((string)$job['concurso']) . ' ' . e(concurso_nome((string)$job['concurso'])) . ' ' . ((int)$job['ano'] > 0 ? (int)$job['ano'] : '') . ')';
        }
        $h .= '.</p>';
        if ($esperadas > $salvas) $h .= '<p>⚠️ Faltam ' . ($esperadas - $salvas) . ' — confira os erros abaixo.</p>';
        if (trim((string)$job['erros']) !== '') $h .= '<p class="muted">Erros: ' . e(mb_substr((string)$job['erros'], 0, 500)) . '</p>';
        $h .= '<form method="post" action="' . e(url('admin/index.php?tab=importar')) . '" onsubmit="return confirm(\'Ativar todas e liberar p/ os alunos?\')">' . csrf_field() . '<input type="hidden" name="form" value="ativar"><input type="hidden" name="arquivo" value="' . $a . '"><button class="btn btn-ok" type="submit">✅ Ativar todas</button></form> ';
        $h .= '<a class="btn" href="' . e(url('admin/index.php?tab=questoes')) . '">Revisar questões</a>';
        $out['doneHtml'] = $h;
    }
    return $out;
}

/** Vincula imagens extraídas às questões (pela posição). Retorna [total, ligadas]. */
function import_link_images(array $pm, string $arquivo, array $ids): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, numero FROM questoes WHERE origem = ?');
    $st->execute([$arquivo]);
    $byNum = [];
    foreach ($st->fetchAll() as $row) {
        if ((int)$row['numero'] > 0) $byNum[(int)$row['numero']] = (int)$row['id'];
    }
    // guarda: se NENHUM marcador bate com os números do banco, o join por número é lixo (ligaria pág. errada) — pula tudo p/ o estimador
    if ($byNum !== []) {
        $overlap = false;
        foreach ($pm['paginas'] as $pg) {
            foreach ($pg['questoes'] as $mk) {
                if (isset($byNum[(int)$mk['n']])) { $overlap = true; break 2; }
            }
        }
        if (!$overlap) return [0, 0];
    }
    // registra página + região vertical de cada questão (p/ print e recorte automático da figura)
    $upPg = $pdo->prepare('UPDATE questoes SET pagina = ?, regiao = ? WHERE origem = ? AND numero = ?');
    $feitas = [];
    foreach ($pm['paginas'] as $pgi => $pg0) {
        $h = max(1.0, (float)($pg0['h'] ?? 842));
        $mks = $pg0['questoes'];
        usort($mks, function ($a, $b) { return $a['y_top'] <=> $b['y_top']; });
        $nm = count($mks);
        for ($k = 0; $k < $nm; $k++) {
            $nn = (int)$mks[$k]['n'];
            if ($nn <= 0 || !isset($byNum[$nn]) || isset($feitas[$nn])) continue;
            $feitas[$nn] = 1;
            $fTop = (float)$mks[$k]['y_top'] / $h;
            if ($fTop < 0 || $fTop > 1) {
                $upPg->execute([$pgi + 1, '', $arquivo, $nn]); // marcador fora da página: só registra a página
                continue;
            }
            $yEnd = ($k + 1 < $nm) ? (float)$mks[$k + 1]['y_top'] : $h; // até a próxima questão ou o rodapé
            $y0 = max(0.0, (float)$mks[$k]['y_top'] / $h - 0.015);
            $y1 = min(1.0, $yEnd / $h + 0.015);
            if ($y1 - $y0 < 0.08) $y1 = min(1.0, $y0 + 0.08);
            $upPg->execute([$pgi + 1, round($y0, 3) . '-' . round($y1, 3), $arquivo, $nn]);
        }
    }
    if ($pm['total'] === 0) return [0, 0];
    $ins = $pdo->prepare('INSERT INTO questao_imagens (questao_id, arquivo, legenda, origem) VALUES (?,?,?,?)');
    $total = 0;
    $lig = 0;
    foreach ($pm['paginas'] as $pg) {
        $marks = $pg['questoes'];
        usort($marks, function ($a, $b) { return $a['y_top'] <=> $b['y_top']; });
        foreach ($pg['imgs'] as $im) {
            $best = null;
            foreach ($marks as $mk) {
                if ($mk['y_top'] <= $im['y_top'] + 2) $best = $mk;
                else break;
            }
            $qid = ($best !== null && isset($byNum[$best['n']])) ? $byNum[$best['n']] : null;
            if ($qid === null && $pm['total'] === 1 && count($ids) === 1) $qid = $ids[0];
            $stX = $pdo->prepare('SELECT id FROM questao_imagens WHERE origem = ? AND arquivo = ?');
            $stX->execute([$arquivo, $im['arquivo']]);
            if ($stX->fetchColumn()) continue; // já registrada (reprocessamento): não duplica
            $ins->execute([$qid, $im['arquivo'], $qid === null ? 'Figura (vincular)' : '', $arquivo]);
            $total++;
            if ($qid !== null) $lig++;
        }
    }
    return [$total, $lig];
}

/** Divide um trecho em 2 metades, cortando no início de uma questão (nunca no meio). */
function import_split_half(string $ch): array {
    $lines = preg_split('/\n+/', trim($ch)) ?: [$ch];
    $marks = [];
    foreach ($lines as $i => $ln) {
        if (preg_match(IMPORT_QMARK, $ln)) $marks[] = $i;
    }
    if ($marks !== []) {
        $mid = (int)(count($lines) / 2);
        $best = $marks[0];
        foreach ($marks as $mi) {
            if (abs($mi - $mid) < abs($best - $mid)) $best = $mi;
        }
        if ($best > 0) return [implode("\n", array_slice($lines, 0, $best)), implode("\n", array_slice($lines, $best))];
    }
    $mid = max(1, (int)(count($lines) / 2));
    return [implode("\n", array_slice($lines, 0, $mid)), implode("\n", array_slice($lines, $mid))];
}

/** Fluxo completo: PDF -> rascunhos (+imagens) no banco. */
function import_process(string $path, int $by, array $opts = []): array {
    $maxChunks = max(1, min(60, (int)($opts['max_chunks'] ?? 15)));
    $arquivo = basename($path);
    $texto = pdf_text($path);
    $len = strlen($texto);
    if ($len < 100) {
        return ['ok' => false, 'erro' => 'Não extraí texto legível deste PDF (talvez seja digitalizado/só imagem ou protegido). Use "👁️ Ver texto" para conferir.', 'chars' => $len];
    }
    $chunks = import_chunk_text($texto);
    $total = count($chunks);
    $esperadas = import_contar_esperadas($texto);
    $inicio = max(0, (int)($opts['inicio'] ?? 0));
    $chunks = array_slice($chunks, $inicio, $maxChunks);
    if ($chunks === []) {
        return ['ok' => false, 'erro' => 'Nada a processar: início além do total de trechos.', 'chars' => $len];
    }
    $det = null;
    if ($inicio > 0) {
        // continuando: reaproveita concurso/ano já detectados (não detecta no meio da prova)
        $stD = db()->prepare('SELECT concurso, ano FROM importacoes WHERE arquivo = ?');
        $stD->execute([$arquivo]);
        if ($row = $stD->fetch()) $det = ['concurso' => (string)$row['concurso'], 'ano' => (int)$row['ano']];
    }
    if ($det === null && in_array(($opts['concurso'] ?? ''), CONCURSOS_TODOS, true) && (int)($opts['ano'] ?? 0) > 0) {
        $det = ['concurso' => (string)$opts['concurso'], 'ano' => (int)$opts['ano']]; // já informado: pula a detecção (economiza 1 chamada)
    }
    if ($det === null) {
        $det = import_detect($chunks[0]);
        if (!empty($det['erro'])) return ['ok' => false, 'erro' => 'Falha na IA (detecção): ' . $det['erro'], 'chars' => $len];
    }
    $concurso = (string)($opts['concurso'] ?? $det['concurso']);
    if (!in_array($concurso, CONCURSOS_TODOS, true)) $concurso = $det['concurso'];
    $ano = (int)($opts['ano'] ?? $det['ano']);
    $ids = [];
    $puladas = 0;
    $erros = [];
    $motivos = [];
    foreach ($chunks as $i => $ch) {
        $tn = $inicio + $i + 1; // número global do trecho
        [$items, $err] = import_extract_chunk($ch, $concurso, $ano);
        if ($err !== null && strlen($ch) > 1200) {
            // retry: divide o trecho ao meio e tenta cada metade (pedaço menor = menos corte)
            $items = [];
            $halfErr = [];
            foreach (import_split_half($ch) as $h) {
                if (trim($h) === '') continue;
                [$it2, $e2] = import_extract_chunk($h, $concurso, $ano);
                if ($e2 !== null) $halfErr[] = $e2;
                else foreach ($it2 as $it) $items[] = $it;
            }
            $err = ($items === []) ? ('trecho ' . $tn . ': ' . $err . ' | retry: ' . implode(' / ', $halfErr)) : null;
        } elseif ($err !== null) {
            $err = 'trecho ' . $tn . ': ' . $err;
        }
        if ($err === null && strlen($ch) > 800 && count($items) < import_contar_esperadas($ch)) {
            // veio menos do que o trecho tem (cortou no fim?): busca o resto nas metades, sem duplicar
            $tem = [];
            foreach ($items as $it) {
                $nn = (int)($it['numero'] ?? 0);
                if ($nn > 0) $tem[$nn] = 1;
            }
            foreach (import_split_half($ch) as $h) {
                if (trim($h) === '') continue;
                [$it2, $e2] = import_extract_chunk($h, $concurso, $ano);
                if ($e2 !== null) continue;
                foreach ($it2 as $it) {
                    $nn = (int)($it['numero'] ?? 0);
                    if ($nn <= 0 || isset($tem[$nn])) continue;
                    $tem[$nn] = 1;
                    $items[] = $it;
                }
            }
        }
        if ($err !== null) {
            $erros[] = $err;
            continue;
        }
        // salva a cada trecho: se o servidor matar o script no meio, o que já foi não se perde
        [$idsC, $pulC, $motC] = import_save_items($items, $arquivo, $concurso, $ano, $by);
        foreach ($idsC as $id) $ids[] = $id;
        $puladas += $pulC;
        foreach ($motC as $mm) {
            if (count($motivos) < 12) $motivos[] = $mm;
        }
        if (!empty($opts['progress']) && is_callable($opts['progress'])) $opts['progress']($tn, $total, count($ids));
    }
    // imagens embutidas -> vínculo automático (+ registra página das questões salvas)
    $imgTotal = 0;
    $imgLig = 0;
    try {
        $prefix = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($arquivo, PATHINFO_FILENAME));
        $prefix = strtolower(trim((string)$prefix, '-')) . '-' . substr(md5($arquivo), 0, 6);
        if ($prefix === '-') $prefix = 'pdf';
        if ($inicio > 0) {
            // continuando: remove só as ainda não vinculadas (evita duplicar); as vinculadas ficam
            $st0 = db()->prepare('SELECT id FROM questao_imagens WHERE origem = ? AND questao_id IS NULL');
            $st0->execute([$arquivo]);
            foreach ($st0->fetchAll(PDO::FETCH_COLUMN) as $iid) questao_excluir_imagem((int)$iid);
        }
        $pm = pdf_media_extract($path, import_uploads_dir(), $prefix);
        [$imgTotal, $imgLig] = import_link_images($pm, $arquivo, $ids);
    } catch (Throwable $e) { /* imagens são bônus: não falham a importação */
    }
    // Estima a página das que ficaram sem (posição do texto no PDF; não gasta IA).
    $npEst = isset($pm) ? count($pm['paginas']) : 0;
    if ($npEst > 0) {
        try { import_estimar_paginas($arquivo, $texto, $npEst); } catch (Throwable $e2) {}
    }
    $printInfo = ['ok' => false, 'paginas' => 0];
    if (!empty($opts['prints'])) {
        try {
            $printInfo = prints_generate($path, $arquivo);
        } catch (Throwable $e) {
            $printInfo = ['ok' => false, 'erro' => $e->getMessage()];
        }
    }
    $pdo = db();
    $feitos = $inicio + count($chunks); // trechos cobertos até aqui (global)
    $falta = max(0, $total - $feitos);
    if ($inicio > 0) {
        // continuando: soma às que já estavam salvas (não apaga nada)
        $st = $pdo->prepare('SELECT id, questoes FROM importacoes WHERE arquivo = ?');
        $st->execute([$arquivo]);
        $ant = $st->fetch();
        if ($ant) {
            $pdo->prepare('UPDATE importacoes SET questoes = questoes + ?, concurso = ?, ano = ? WHERE id = ?')->execute([count($ids), $concurso, $ano, $ant['id']]);
        } else {
            $pdo->prepare('INSERT INTO importacoes (arquivo,concurso,ano,questoes,status) VALUES (?,?,?,?,\'rascunho\')')->execute([$arquivo, $concurso, $ano, count($ids)]);
        }
    } else {
        $pdo->prepare('DELETE FROM importacoes WHERE arquivo = ?')->execute([$arquivo]);
        $pdo->prepare('INSERT INTO importacoes (arquivo,concurso,ano,questoes,status) VALUES (?,?,?,?,\'rascunho\')')->execute([$arquivo, $concurso, $ano, count($ids)]);
    }
    $stT = $pdo->prepare('SELECT COUNT(*) FROM questoes WHERE origem = ?');
    $stT->execute([$arquivo]);
    return [
        'ok' => true, 'arquivo' => $arquivo, 'concurso' => $concurso, 'ano' => $ano,
        'chars' => $len, 'trechos' => $total, 'processados' => $feitos, 'inicio' => $inicio,
        'proximo' => $feitos, 'falta' => $falta, 'esperadas' => $esperadas,
        'ids' => $ids, 'puladas' => $puladas, 'motivos' => $motivos, 'erros' => $erros,
        'total_salvas' => (int)$stT->fetchColumn(),
        'imagens' => $imgTotal, 'img_ligadas' => $imgLig, 'prints' => $printInfo,
    ];
}

/** Ativa todas as questões de uma importação. Retorna qtd. */
function import_ativar(string $arquivo): int {
    $st = db()->prepare('UPDATE questoes SET ativo = 1 WHERE origem = ?');
    $st->execute([$arquivo]);
    db()->prepare("UPDATE importacoes SET status = 'ativo' WHERE arquivo = ?")->execute([$arquivo]);
    return $st->rowCount();
}

/** Exclui questões + imagens importadas de um arquivo + registro. Retorna qtd questões. */
function import_excluir(string $arquivo): int {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM questoes WHERE origem = ?');
    $st->execute([$arquivo]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $qid) {
        foreach (['respostas', 'favoritos', 'caderno_erros', 'comentarios'] as $tb) {
            $pdo->prepare("DELETE FROM $tb WHERE questao_id = ?")->execute([$qid]);
        }
        $pdo->prepare('DELETE FROM questoes WHERE id = ?')->execute([$qid]);
    }
    $st = $pdo->prepare('SELECT id FROM questao_imagens WHERE origem = ?');
    $st->execute([$arquivo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $iid) questao_excluir_imagem((int)$iid);
    $st = $pdo->prepare('SELECT arquivo_img FROM import_paginas WHERE arquivo = ?');
    $st->execute([$arquivo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pf) @unlink(import_prints_dir() . '/' . basename((string)$pf));
    $pdo->prepare('DELETE FROM import_paginas WHERE arquivo = ?')->execute([$arquivo]);
    $pdo->prepare('DELETE FROM importacoes WHERE arquivo = ?')->execute([$arquivo]);
    return count($ids);
}

/* ---------------- Prints das páginas (screenshot) ---------------- */

function import_prints_dir(): string {
    $d = APP_ROOT . '/assets/uploads/paginas';
    if (!is_dir($d)) mkdir($d, 0775, true);
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '<!-- protegido -->');
    return $d;
}

/** Backend de renderização disponível: pdftoppm | gs | imagick | null. */
function prints_backend(): ?string {
    if (function_exists('exec')) {
        $out = [];
        $code = 1;
        @exec('pdftoppm -v 2>&1', $out, $code);
        if ($code === 0) return 'pdftoppm';
        $out = [];
        $code = 1;
        @exec('gs --version 2>&1', $out, $code);
        if ($code === 0) return 'gs';
    }
    if (extension_loaded('imagick')) return 'imagick';
    return null;
}

function prints_slug(string $arquivo): string {
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', pathinfo($arquivo, PATHINFO_FILENAME)), '-'));
    return $slug !== '' ? $slug : 'pdf';
}

/** Renderiza páginas do PDF em PNG e registra. Retorna ['ok'=>, 'paginas'=>n, 'erro'=>?]. */
function prints_generate(string $path, string $arquivo, int $dpi = 150, int $maxPages = 40): array {
    $backend = prints_backend();
    if ($backend === null) {
        return ['ok' => false, 'erro' => 'Sem renderizador neste servidor (precisa pdftoppm, Ghostscript ou Imagick). Sem problema: os alunos já veem o PDF original automaticamente. Se quiser os PNGs (mais rápidos), gere localmente com tools/prints_pdf.php e envie.'];
    }
    $dpi = max(72, min(300, $dpi));
    $maxPages = max(1, min(100, $maxPages));
    $dir = import_prints_dir();
    $slug = prints_slug($arquivo);
    foreach (glob($dir . '/' . $slug . '-*.png') ?: [] as $old) @unlink($old);
    foreach (glob($dir . '/' . $slug . '-*.jpg') ?: [] as $old) @unlink($old);
    db()->prepare('DELETE FROM import_paginas WHERE arquivo = ?')->execute([$arquivo]);
    if ($backend === 'pdftoppm') {
        @exec('pdftoppm -png -r ' . $dpi . ' -f 1 -l ' . $maxPages . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($dir . '/' . $slug) . ' 2>&1', $o, $c);
        if ($c !== 0) return ['ok' => false, 'erro' => 'pdftoppm falhou ao renderizar.'];
    } elseif ($backend === 'gs') {
        @exec('gs -dBATCH -dNOPAUSE -sDEVICE=png16m -r' . $dpi . ' -dFirstPage=1 -dLastPage=' . $maxPages . ' -sOutputFile=' . escapeshellarg($dir . '/' . $slug . '-%d.png') . ' ' . escapeshellarg($path) . ' 2>&1', $o, $c);
        if ($c !== 0) return ['ok' => false, 'erro' => 'Ghostscript falhou ao renderizar.'];
    } else {
        try {
            $im = new Imagick();
            $im->setResolution($dpi, $dpi);
            $im->readImage($path);
            $n = min($im->getNumberImages(), $maxPages);
            for ($i = 0; $i < $n; $i++) {
                $im->setIteratorIndex($i);
                $im->setImageFormat('png');
                $im->writeImage($dir . '/' . $slug . '-' . ($i + 1) . '.png');
            }
            $im->clear();
        } catch (Throwable $e) {
            return ['ok' => false, 'erro' => 'Imagick falhou: ' . $e->getMessage()];
        }
    }
    $files = glob($dir . '/' . $slug . '-*.png') ?: [];
    natcasesort($files);
    $ins = db()->prepare('INSERT INTO import_paginas (arquivo, pagina, arquivo_img) VALUES (?,?,?)');
    $seq = 0;
    foreach ($files as $fp) {
        $base = basename($fp);
        $pg = preg_match('/-(\d+)\.png$/', $base, $m) ? (int)$m[1] : 0;
        if ($pg <= 0) $pg = ++$seq;
        else $seq = max($seq, $pg);
        $ins->execute([$arquivo, $pg, $base]);
    }
    $st = db()->prepare('SELECT COUNT(*) FROM import_paginas WHERE arquivo = ?');
    $st->execute([$arquivo]);
    return ['ok' => true, 'paginas' => (int)$st->fetchColumn(), 'backend' => $backend];
}
