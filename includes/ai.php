<?php
declare(strict_types=1);

/**
 * Papiro IA — rota de chat otimizada para baixa latência.
 * A importação de PDFs continua em import_lib.php, pois tem outro perfil de carga.
 */

function ai_has_key(): bool {
    return GROQ_API_KEY !== '' || GEMINI_API_KEY !== '' || MISTRAL_API_KEY !== '';
}

function ai_provider_for_model(string $model): array {
    if (str_starts_with($model, 'gemini')) {
        return ['gemini', 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', GEMINI_API_KEY];
    }
    if (str_starts_with($model, 'mistral')) {
        return ['mistral', 'https://api.mistral.ai/v1/chat/completions', MISTRAL_API_KEY];
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
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $key,
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

/**
 * Ordem deliberadamente focada em velocidade para chat.
 * O modelo grande do Groq fica como último recurso para perguntas difíceis.
 */
function ai_fast_candidates(): array {
    $models = [];
    if (GROQ_API_KEY !== '') {
        $models[] = (string)pm_cfg('AI_FAST_GROQ_MODEL', 'openai/gpt-oss-20b');
    }
    if (GEMINI_API_KEY !== '') {
        $models[] = (string)pm_cfg('AI_FAST_GEMINI_MODEL', 'gemini-2.5-flash-lite');
        $models[] = GEMINI_MODEL;
    }
    if (MISTRAL_API_KEY !== '') {
        $models[] = (string)pm_cfg('AI_FAST_MISTRAL_MODEL', 'mistral-small-latest');
    }
    if (GROQ_API_KEY !== '') {
        $models[] = GROQ_MODEL;
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
