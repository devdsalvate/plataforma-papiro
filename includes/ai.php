<?php
declare(strict_types=1);

function ai_has_key(): bool {
    return GROQ_API_KEY !== '' || GEMINI_API_KEY !== '';
}

function ai_provider_for_model(string $model): array {
    if (str_starts_with($model, 'gemini')) {
        return ['gemini', 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', GEMINI_API_KEY];
    }
    return ['groq', 'https://api.groq.com/openai/v1/chat/completions', GROQ_API_KEY];
}

/** @return array{ok:bool,text:string,provider:string,model:string,ms:int,error:string} */
function ai_openai_call(string $model, array $messages, int $maxTokens = 720, float $temperature = 0.25): array {
    [$provider, $url, $key] = ai_provider_for_model($model);
    if ($key === '') return ['ok'=>false,'text'=>'','provider'=>$provider,'model'=>$model,'ms'=>0,'error'=>'sem_chave'];

    $body = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => max(120, min(1200, $maxTokens)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) return ['ok'=>false,'text'=>'','provider'=>$provider,'model'=>$model,'ms'=>0,'error'=>'json'];

    $start = microtime(true);
    $out = false; $code = 0; $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 22,
        ]);
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string)curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'content' => $body, 'timeout' => 22, 'ignore_errors' => true,
            'header' => "Content-Type: application/json
Authorization: Bearer " . $key,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        $code = $out === false ? 0 : 200;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $m)) $code = (int)$m[1];
        if ($out === false) $err = 'falha_http';
    }
    $ms = (int)round((microtime(true) - $start) * 1000);
    if ($out === false) return ['ok'=>false,'text'=>'','provider'=>$provider,'model'=>$model,'ms'=>$ms,'error'=>$err ?: 'rede'];

    $j = json_decode((string)$out, true);
    $text = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    if ($code >= 200 && $code < 300 && $text !== '') {
        return ['ok'=>true,'text'=>$text,'provider'=>$provider,'model'=>$model,'ms'=>$ms,'error'=>''];
    }
    $msg = (string)($j['error']['message'] ?? $err ?: ('HTTP ' . $code));
    return ['ok'=>false,'text'=>'','provider'=>$provider,'model'=>$model,'ms'=>$ms,'error'=>$msg];
}

function ai_fast_candidates(): array {
    $models = [];
    if (GROQ_API_KEY !== '') {
        $models[] = (string)pm_cfg('AI_FAST_GROQ_MODEL', 'openai/gpt-oss-20b');
        $models[] = GROQ_MODEL;
    }
    if (GEMINI_API_KEY !== '') {
        $models[] = (string)pm_cfg('AI_FAST_GEMINI_MODEL', 'gemini-2.5-flash-lite');
        $models[] = GEMINI_MODEL;
    }
    return array_values(array_unique(array_filter($models)));
}

/** @return array{ok:bool,text:string,provider:string,model:string,ms:int,error:string} */
function ai_chat_fast(array $messages, int $maxTokens = 720): array {
    $last = ['ok'=>false,'text'=>'','provider'=>'','model'=>'','ms'=>0,'error'=>'Nenhum provedor configurado'];
    foreach (ai_fast_candidates() as $model) {
        $r = ai_openai_call((string)$model, $messages, $maxTokens, 0.25);
        $last = $r;
        if ($r['ok']) return $r;
    }
    return $last;
}

function ai_grading_candidates(): array {
    $models = [];
    if (GROQ_API_KEY !== '') {
        $models[] = GROQ_MODEL;
        $models[] = (string)pm_cfg('AI_FAST_GROQ_MODEL', 'openai/gpt-oss-20b');
    }
    if (GEMINI_API_KEY !== '') {
        $models[] = GEMINI_MODEL;
        $models[] = (string)pm_cfg('AI_FAST_GEMINI_MODEL', 'gemini-2.5-flash-lite');
    }
    return array_values(array_unique(array_filter($models)));
}

function ai_json_object(string $text): ?array {
    $text = trim($text);
    $direct = json_decode($text, true);
    if (is_array($direct)) return $direct;
    if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) return $j;
    }
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/si', $text, $m)) {
        $j = json_decode($m[1], true);
        if (is_array($j)) return $j;
    }
    return null;
}

function ai_answer_index($value): int {
    if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
        $n = (int)$value;
        if ($n === 0) return 0;
        if ($n >= 1 && $n <= 5) return $n - 1;
        return -1;
    }
    $s = strtoupper(trim((string)$value));
    if (preg_match('/([A-E])/', $s, $m)) return ord($m[1]) - 65;
    return -1;
}

/** @return array{ok:bool,answer:int,confidence:float,needs_visual:bool,explanation:string,model:string,provider:string} */
function ai_grade_once(string $model, array $q): array {
    $alts = [];
    foreach (['alt_a','alt_b','alt_c','alt_d','alt_e'] as $i => $k) {
        $v = trim((string)($q[$k] ?? ''));
        if ($v !== '') $alts[] = chr(65 + $i) . ') ' . $v;
    }
    $hasVisual = (int)($q['exibir_preview'] ?? 0) === 1;
    $question = trim((string)($q['enunciado'] ?? ''));
    if ($question === '' || count($alts) < 2) {
        return ['ok'=>false,'answer'=>-1,'confidence'=>0.0,'needs_visual'=>false,'explanation'=>'','model'=>$model,'provider'=>''];
    }

    $system = <<<'TXT'
Você é um corretor técnico de questões de concursos militares brasileiros. Resolva a questão por raciocínio próprio. Não invente informação que não esteja no texto. Se a resposta depender de uma figura, gráfico, tabela, mapa, tirinha ou diagrama que não foi fornecido em texto, marque needs_visual=true e NÃO chute.

Responda SOMENTE com JSON válido, sem markdown, no formato:
{"answer":"A","confidence":0.97,"needs_visual":false,"explanation":"explicação curta e objetiva"}

Regras:
- answer deve ser A, B, C, D ou E.
- confidence deve ser número de 0 a 1.
- Se houver ambiguidade real, use confidence baixa.
- Não confunda "alternativa escolhida pelo aluno" com gabarito; você não receberá a resposta do aluno.
- Confira contas, sinais, unidades, gramática e exceções antes de responder.
TXT;

    $user = "Concurso: " . (string)($q['concurso'] ?? '') . "
"
          . "Ano: " . (string)($q['ano'] ?? '') . "
"
          . "Matéria: " . (string)($q['materia'] ?? '') . "
"
          . "Assunto: " . (string)($q['assunto'] ?? '') . "
"
          . "Há apoio visual cadastrado: " . ($hasVisual ? 'SIM' : 'NÃO') . "

"
          . "ENUNCIADO:
" . $question . "

ALTERNATIVAS:
" . implode("
", $alts);

    $r = ai_openai_call($model, [
        ['role'=>'system','content'=>$system],
        ['role'=>'user','content'=>$user],
    ], 650, 0.05);

    if (!$r['ok']) {
        return ['ok'=>false,'answer'=>-1,'confidence'=>0.0,'needs_visual'=>false,'explanation'=>'','model'=>$model,'provider'=>$r['provider']];
    }
    $j = ai_json_object($r['text']);
    if (!$j) return ['ok'=>false,'answer'=>-1,'confidence'=>0.0,'needs_visual'=>false,'explanation'=>'','model'=>$model,'provider'=>$r['provider']];

    $answer = ai_answer_index($j['answer'] ?? $j['gabarito'] ?? -1);
    $confidence = (float)($j['confidence'] ?? $j['confianca'] ?? 0);
    if ($confidence > 1 && $confidence <= 100) $confidence /= 100;
    $confidence = max(0.0, min(1.0, $confidence));
    $needsVisual = filter_var($j['needs_visual'] ?? false, FILTER_VALIDATE_BOOL);
    $explanation = trim((string)($j['explanation'] ?? $j['explicacao'] ?? ''));
    return [
        'ok'=>$answer >= 0 && $answer <= 4,
        'answer'=>$answer,
        'confidence'=>$confidence,
        'needs_visual'=>$needsVisual,
        'explanation'=>$explanation,
        'model'=>$model,
        'provider'=>$r['provider'],
    ];
}

function ai_grade_question(array $q): array {
    if ((int)($q['exibir_preview'] ?? 0) === 1) {
        return ['accepted'=>false,'answer'=>-1,'confidence'=>0.0,'explanation'=>'','source'=>'','attempted'=>0,'reason'=>'visual'];
    }
    $candidates = ai_grading_candidates();
    if (!$candidates) return ['accepted'=>false,'answer'=>-1,'confidence'=>0.0,'explanation'=>'','source'=>'','attempted'=>0,'reason'=>'sem_chave'];

    $results = [];
    foreach (array_slice($candidates, 0, 2) as $model) {
        $r = ai_grade_once((string)$model, $q);
        if ($r['ok'] && !$r['needs_visual']) $results[] = $r;
    }
    if (!$results) return ['accepted'=>false,'answer'=>-1,'confidence'=>0.0,'explanation'=>'','source'=>'','attempted'=>min(2,count($candidates)),'reason'=>'inconclusivo'];

    if (count($results) === 1) {
        $r = $results[0];
        $ok = $r['confidence'] >= 0.97;
        return [
            'accepted'=>$ok,'answer'=>$ok?$r['answer']:-1,'confidence'=>$r['confidence'],
            'explanation'=>$r['explanation'],'source'=>$ok?'ia_alta_confianca':'',
            'attempted'=>1,'reason'=>$ok?'':'baixa_confianca'
        ];
    }

    usort($results, fn($a,$b) => $b['confidence'] <=> $a['confidence']);
    $a=$results[0]; $b=$results[1];
    $avg=($a['confidence']+$b['confidence'])/2;
    $agree=$a['answer']===$b['answer'];
    $ok=$agree && min($a['confidence'],$b['confidence'])>=0.72 && $avg>=0.86;
    return [
        'accepted'=>$ok,'answer'=>$ok?$a['answer']:-1,'confidence'=>$avg,
        'explanation'=>$a['explanation'],'source'=>$ok?'ia_consenso':'',
        'attempted'=>2,'reason'=>$ok?'':($agree?'baixa_confianca':'sem_consenso')
    ];
}
