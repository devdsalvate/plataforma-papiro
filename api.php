<?php
declare(strict_types=1);
/* Papiro Máximo — API interna (fetch do JS). Todas as ações exigem login + CSRF. */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function jexit(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($_POST['action'] ?? '');
if (!csrf_check($_POST['csrf'] ?? null)) jexit(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 403);
$user = current_user();
if (!$user) jexit(['ok' => false, 'error' => 'Entre para continuar.'], 401);
$uid = (int)$user['id'];
$pdo = db();

/* ---------- IA (Groq) ---------- */
function ia_format(string $t): string {
    $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t) ?? $t;
    return nl2br($t);
}
function groq_chat(array $messages): ?string {
    if (GROQ_API_KEY === '') return null;
    $models = array_values(array_unique(array_merge([GROQ_MODEL], GROQ_FALLBACKS)));
    foreach ($models as $model) {
        $payload = json_encode(['model' => $model, 'messages' => $messages, 'temperature' => 0.4, 'max_tokens' => 1200]);
        $out = false;
        $code = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY],
                CURLOPT_TIMEOUT => 40,
            ]);
            $out = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($out === false) return null;
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'content' => $payload, 'timeout' => 40, 'ignore_errors' => true,
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . GROQ_API_KEY,
            ]]);
            $out = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);
            if ($out === false) return null;
            $code = 200;
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $code = (int)$m[1];
        }
        $j = json_decode((string)$out, true);
        if ($code >= 200 && $code < 300) {
            return isset($j['choices'][0]['message']['content']) ? (string)$j['choices'][0]['message']['content'] : null;
        }
        // modelo inexistente/descontinuado? tenta o próximo; outro erro? desiste
        $msg = strtolower((string)($j['error']['message'] ?? ''));
        $modelErr = $code === 404 || str_contains($msg, 'does not exist') || str_contains($msg, 'decommissioned') || str_contains($msg, 'not found');
        if (!$modelErr) return null;
    }
    return null;
}

switch ($action) {

    case 'responder': {
        $qid = (int)($_POST['qid'] ?? 0);
        $alt = (int)($_POST['alt'] ?? -1);
        $tempo = max(0, min(7200, (int)($_POST['tempo'] ?? 0)));
        $st = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND ativo = 1');
        $st->execute([$qid]);
        $q = $st->fetch();
        if (!$q || $alt < 0 || $alt > 4) jexit(['ok' => false, 'error' => 'Questão inválida.'], 400);
        $gab = (int)$q['gabarito'];
        $correta = $alt === $gab ? 1 : 0;
        $pdo->prepare('INSERT INTO respostas (user_id, questao_id, alternativa, correta, tempo_seg, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $qid, $alt, $correta, $tempo, now_str()]);
        if (!$correta) {
            try {
                $pdo->prepare('INSERT INTO caderno_erros (user_id, questao_id, motivo, created_at) VALUES (?,?,?,?)')
                    ->execute([$uid, $qid, 'Errou a questão', now_str()]);
            } catch (Throwable $e) { /* já está no caderno */ }
        }
        touch_atividade($uid);
        jexit(['ok' => true, 'correta' => (bool)$correta, 'gabarito' => $gab, 'letra' => LETRAS[$gab], 'resolucao' => $q['resolucao']]);
    }

    case 'favorito': {
        $qid = (int)($_POST['qid'] ?? 0);
        $st = $pdo->prepare('SELECT 1 FROM favoritos WHERE user_id = ? AND questao_id = ?');
        $st->execute([$uid, $qid]);
        if ($st->fetchColumn()) {
            $pdo->prepare('DELETE FROM favoritos WHERE user_id = ? AND questao_id = ?')->execute([$uid, $qid]);
            jexit(['ok' => true, 'fav' => false]);
        }
        $pdo->prepare('INSERT INTO favoritos (user_id, questao_id, created_at) VALUES (?,?,?)')->execute([$uid, $qid, now_str()]);
        jexit(['ok' => true, 'fav' => true]);
    }

    case 'comentario': {
        $qid = (int)($_POST['qid'] ?? 0);
        $texto = trim((string)($_POST['texto'] ?? ''));
        if (mb_strlen($texto) < 2 || mb_strlen($texto) > 2000) jexit(['ok' => false, 'error' => 'Comentário deve ter de 2 a 2000 caracteres.'], 400);
        $st = $pdo->prepare('SELECT id FROM questoes WHERE id = ? AND ativo = 1');
        $st->execute([$qid]);
        if (!$st->fetchColumn()) jexit(['ok' => false, 'error' => 'Questão inválida.'], 400);
        $pdo->prepare('INSERT INTO comentarios (user_id, questao_id, texto, aprovado, created_at) VALUES (?,?,?,?,?)')
            ->execute([$uid, $qid, $texto, 1, now_str()]);
        jexit(['ok' => true, 'nome' => $user['nome']]);
    }

    case 'sessao_inicio': {
        $pdo->prepare('INSERT INTO study_sessions (user_id, inicio, tipo, created_at) VALUES (?,?,?,?)')
            ->execute([$uid, now_str(), 'livre', now_str()]);
        jexit(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    case 'sessao_fim': {
        $sid = (int)($_POST['id'] ?? 0);
        $seg = (int)($_POST['seg'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM study_sessions WHERE id = ? AND user_id = ?');
        $st->execute([$sid, $uid]);
        $s = $st->fetch();
        if (!$s) jexit(['ok' => false, 'error' => 'Sessão não encontrada.'], 404);
        if ($seg <= 0) $seg = max(0, time() - strtotime((string)$s['inicio']));
        $seg = min($seg, 12 * 3600);
        $pdo->prepare('UPDATE study_sessions SET fim = ?, duracao_seg = ? WHERE id = ?')->execute([now_str(), $seg, $sid]);
        if ($seg >= 60) touch_atividade($uid);
        jexit(['ok' => true, 'seg' => $seg]);
    }

    case 'trilha_toggle': {
        $mid = (int)($_POST['modulo'] ?? 0);
        $st = $pdo->prepare('SELECT trilha_id FROM trilha_modulos WHERE id = ?');
        $st->execute([$mid]);
        $tid = $st->fetchColumn();
        if (!$tid) jexit(['ok' => false, 'error' => 'Módulo inválido.'], 400);
        $st = $pdo->prepare('SELECT 1 FROM trilha_progresso WHERE user_id = ? AND modulo_id = ?');
        $st->execute([$uid, $mid]);
        if ($st->fetchColumn()) {
            $pdo->prepare('DELETE FROM trilha_progresso WHERE user_id = ? AND modulo_id = ?')->execute([$uid, $mid]);
            $done = false;
        } else {
            $pdo->prepare('INSERT INTO trilha_progresso (user_id, modulo_id, created_at) VALUES (?,?,?)')->execute([$uid, $mid, now_str()]);
            $done = true;
            touch_atividade($uid);
        }
        [, , $pct] = trilha_progresso($uid, (int)$tid);
        jexit(['ok' => true, 'done' => $done, 'pct' => $pct]);
    }

    case 'caderno_salvar': {
        $qid = (int)($_POST['qid'] ?? 0);
        $anot = trim((string)($_POST['anotacao'] ?? ''));
        $pdo->prepare('UPDATE caderno_erros SET anotacao = ? WHERE user_id = ? AND questao_id = ?')->execute([$anot, $uid, $qid]);
        jexit(['ok' => true]);
    }

    case 'caderno_revisada': {
        $qid = (int)($_POST['qid'] ?? 0);
        $v = (int)($_POST['v'] ?? 0) ? 1 : 0;
        $pdo->prepare('UPDATE caderno_erros SET revisada = ? WHERE user_id = ? AND questao_id = ?')->execute([$v, $uid, $qid]);
        jexit(['ok' => true]);
    }

    case 'caderno_remover': {
        $qid = (int)($_POST['qid'] ?? 0);
        $pdo->prepare('DELETE FROM caderno_erros WHERE user_id = ? AND questao_id = ?')->execute([$uid, $qid]);
        jexit(['ok' => true]);
    }

    case 'ia': {
        $pergunta = trim((string)($_POST['pergunta'] ?? ''));
        $qctxId = (int)($_POST['questao_id'] ?? 0);
        if (mb_strlen($pergunta) < 3) jexit(['ok' => false, 'error' => 'Pergunta muito curta.'], 400);
        $ctxQ = null;
        if ($qctxId > 0) {
            $st = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND ativo = 1');
            $st->execute([$qctxId]);
            $ctxQ = $st->fetch() ?: null;
        }
        $demo = GROQ_API_KEY === '';
        if ($demo) {
            if ($ctxQ) {
                $resposta = '<b>📚 Resolução cadastrada:</b><br>' . $ctxQ['resolucao']
                    . '<br><span class="muted">Modo demonstração — configure GROQ_API_KEY para respostas geradas por IA.</span>';
            } else {
                $resposta = 'Estou em <b>modo demonstração</b> (sem chave da Groq). Abra uma <a href="' . e(url('questoes.php')) . '">questão</a> e use a resolução comentada, ou estude pelo <a href="' . e(url('guia.php')) . '">Guia de estudos</a>. 📚';
            }
        } else {
            $system = 'Você é o Papiro IA, tutor de concursos militares brasileiros (EFOMM, EPCAR, EEAR, Colégio Naval, ITA). Responda em português, didático e direto, com passo a passo. Pode usar **negrito** para destacar.';
            $userMsg = $pergunta;
            if ($ctxQ) {
                $alts = q_alternativas($ctxQ);
                $linhas = [];
                foreach ($alts as $i => $a) $linhas[] = LETRAS[$i] . ') ' . $a;
                $userMsg .= "\n\nContexto da questão (" . $ctxQ['concurso'] . ' ' . $ctxQ['ano'] . ' — ' . $ctxQ['materia'] . ' / ' . $ctxQ['assunto'] . '): '
                    . strip_tags($ctxQ['enunciado']) . ' Alternativas: ' . implode(' | ', $linhas)
                    . ' Gabarito oficial: letra ' . LETRAS[(int)$ctxQ['gabarito']] . '. Referência: ' . strip_tags($ctxQ['resolucao']);
            }
            $raw = groq_chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $userMsg]]);
            if ($raw === null || trim($raw) === '') {
                $resposta = $ctxQ
                    ? ('⚠️ A IA está indisponível agora. Segue a resolução cadastrada:<br>' . $ctxQ['resolucao'])
                    : '⚠️ A IA está indisponível agora. Tente novamente em instantes.';
            } else {
                $resposta = ia_format($raw);
            }
        }
        $pdo->prepare('INSERT INTO ia_perguntas (user_id, questao_id, pergunta, resposta, created_at) VALUES (?,?,?,?,?)')
            ->execute([$uid, $ctxQ ? (int)$ctxQ['id'] : null, mb_substr($pergunta, 0, 1000), $resposta, now_str()]);
        jexit(['ok' => true, 'resposta' => $resposta, 'demo' => $demo]);
    }

    case 'job_step': {
        // Um passo da importação em 2º plano (1 trecho). Admin pode sair da página e voltar: retoma do cursor.
        if (!is_admin()) jexit(['ok' => false, 'error' => 'Sem permissão.'], 403);
        require_once __DIR__ . '/includes/import_lib.php';
        $job = job_get((int)($_POST['id'] ?? 0));
        if (!$job) jexit(['ok' => false, 'error' => 'Trabalho não encontrado.'], 404);
        if ($job['status'] === 'concluido') {
            jexit(job_api_state($job, true));
        }
        if ($job['status'] === 'rodando' && (time() - strtotime((string)$job['updated_at'])) < 120) {
            jexit(['ok' => false, 'busy' => true]);
        }
        @set_time_limit(300);
        $r = job_step($job, $uid);
        if (empty($r['ok'])) jexit(['ok' => false, 'error' => $r['erro'] ?? 'Falha no passo.']);
        jexit(job_api_state(job_get((int)$job['id']) ?: $job, !empty($r['done']), $r['res'] ?? []));
    }

    default:
        jexit(['ok' => false, 'error' => 'Ação inválida.'], 400);
}
