<?php
declare(strict_types=1);
/* Papiro Máximo — API interna (fetch do JS). Todas as ações exigem login + CSRF. */
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai.php';
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

/* ---------- IA ---------- */
function ia_format(string $t): string {
    $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t) ?? $t;
    return nl2br($t);
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
        if ($gab < 0 || $gab > 4) {
            jexit([
                'ok' => true, 'avaliavel' => false,
                'message' => 'Esta questão veio do PDF oficial e o gabarito ainda não foi validado no banco. A prévia original continua disponível para estudo.'
            ]);
        }
        $correta = $alt === $gab ? 1 : 0;
        $pdo->prepare('INSERT INTO respostas (user_id, questao_id, alternativa, correta, tempo_seg, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $qid, $alt, $correta, $tempo, now_str()]);
        if (!$correta) {
            try {
                $pdo->prepare('INSERT INTO caderno_erros (user_id, questao_id, motivo, created_at) VALUES (?,?,?,?)')
                    ->execute([$uid, $qid, 'Errou a questão', now_str()]);
            } catch (Throwable $e) { /* já está no caderno */ }
        }
        if (!empty($_POST['simulado']) && !empty($_SESSION['simulado_atual']['ids'])) {
            $_SESSION['simulado_atual']['answers'][(string)$qid] = ['alt' => $alt, 'correta' => (bool)$correta];
        }
        touch_atividade($uid);
        jexit(['ok' => true, 'avaliavel' => true, 'correta' => (bool)$correta, 'gabarito' => $gab, 'letra' => LETRAS[$gab], 'resolucao' => $q['resolucao']]);
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
        if (mb_strlen($pergunta) > 2500) $pergunta = mb_substr($pergunta, 0, 2500);

        $ctxQ = null;
        if ($qctxId > 0) {
            $st = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND ativo = 1');
            $st->execute([$qctxId]);
            $ctxQ = $st->fetch() ?: null;
        }

        // Ações simples do produto: a IA reconhece pedidos de simulado e leva o aluno
        // para o gerador já filtrado, sem gastar uma chamada de modelo.
        if (!$ctxQ && preg_match('/\bsimulado\b/iu', $pergunta)) {
            $qtd = 10;
            if (preg_match('/\b(5|10|15|20|30|40)\b/', $pergunta, $mq)) $qtd = (int)$mq[1];
            $mat = '';
            foreach (MATERIAS as $m) if ($m !== 'Geral' && stripos($pergunta, $m) !== false) { $mat = $m; break; }
            $conc = '';
            foreach (CONCURSOS_TODOS as $c) if ($c !== 'Outro' && stripos($pergunta, $c) !== false) { $conc = $c; break; }
            $href = url('simulados.php?' . http_build_query(['gerar'=>1,'qtd'=>$qtd,'materia'=>$mat,'concurso'=>$conc]));
            $resposta = 'Posso montar isso direto no gerador. <a class="ai-action" href="' . e($href) . '">Abrir gerador de simulado</a>';
            $pdo->prepare('INSERT INTO ia_perguntas (user_id, questao_id, pergunta, resposta, created_at) VALUES (?,?,?,?,?)')
                ->execute([$uid, null, mb_substr($pergunta, 0, 1000), $resposta, now_str()]);
            jexit(['ok'=>true,'resposta'=>$resposta,'demo'=>false,'action'=>'simulado']);
        }

        $demo = !ai_has_key();
        $meta = null;
        if ($demo) {
            if ($ctxQ && trim((string)$ctxQ['resolucao']) !== '') {
                $resposta = '<b>Resolução cadastrada:</b><br>' . $ctxQ['resolucao']
                    . '<br><span class="muted">Configure uma chave Gemini, Groq ou Mistral para explicações geradas pela IA.</span>';
            } else {
                $resposta = 'A IA ainda não tem uma chave configurada. Você pode continuar usando o banco de questões e os simulados; para ativar o tutor, configure Gemini, Groq ou Mistral em <code>includes/config.local.php</code>.';
            }
        } else {
            $system = 'Você é Papiro IA, tutor de provas militares brasileiras. Responda em português brasileiro, de forma curta, clara e prática. Para matemática/física/química, mostre os passos essenciais e a fórmula usada. Não invente dados ausentes. Quando houver uma questão oficial, diferencie claramente o que está no enunciado do que é sua explicação. Evite emojis e introduções longas.';
            $messages = [['role'=>'system','content'=>$system]];

            // Só dois turnos anteriores: mantém continuidade sem inflar tokens/latência.
            $hst = $pdo->prepare('SELECT pergunta, resposta FROM ia_perguntas WHERE user_id = ? ORDER BY id DESC LIMIT 2');
            $hst->execute([$uid]);
            foreach (array_reverse($hst->fetchAll()) as $h) {
                $messages[] = ['role'=>'user','content'=>mb_substr(strip_tags((string)$h['pergunta']),0,900)];
                $messages[] = ['role'=>'assistant','content'=>mb_substr(strip_tags((string)$h['resposta']),0,1200)];
            }

            $userMsg = $pergunta;
            if ($ctxQ) {
                $alts = q_alternativas($ctxQ);
                $linhas = [];
                foreach ($alts as $i => $a) if (trim($a) !== '') $linhas[] = LETRAS[$i] . ') ' . mb_substr(strip_tags($a),0,500);
                $userMsg .= "\n\nQUESTÃO OFICIAL NO CONTEXTO:\nConcurso: " . $ctxQ['concurso'] . ' · ' . $ctxQ['ano']
                    . "\nMatéria: " . $ctxQ['materia'] . ' · ' . $ctxQ['assunto']
                    . "\nEnunciado extraído: " . mb_substr(strip_tags((string)$ctxQ['enunciado']),0,2500)
                    . (empty($linhas) ? '' : "\nAlternativas: " . implode(' | ', $linhas));
                $gab = (int)$ctxQ['gabarito'];
                if ($gab >= 0 && $gab <= 4) $userMsg .= "\nGabarito validado: " . LETRAS[$gab];
                if (trim((string)$ctxQ['resolucao']) !== '') $userMsg .= "\nReferência cadastrada: " . mb_substr(strip_tags((string)$ctxQ['resolucao']),0,1600);
                if (!empty($ctxQ['origem']) && (int)($ctxQ['pagina'] ?? 0) > 0) $userMsg .= "\nFonte visual: " . basename((string)$ctxQ['origem']) . ', página ' . (int)$ctxQ['pagina'] . '. Se o texto extraído estiver incompleto, avise para o aluno conferir a prévia visual.';
            }
            $messages[] = ['role'=>'user','content'=>$userMsg];
            $r = ai_chat_fast($messages, 720);
            $meta = ['provider'=>$r['provider'],'model'=>$r['model'],'ms'=>$r['ms']];
            if (!$r['ok']) {
                $resposta = $ctxQ && trim((string)$ctxQ['resolucao']) !== ''
                    ? ('A IA não respondeu agora. Segue a resolução cadastrada:<br>' . $ctxQ['resolucao'])
                    : 'A IA não respondeu agora. Verifique a chave/API e tente novamente.';
            } else {
                $resposta = ia_format($r['text']);
            }
        }
        $pdo->prepare('INSERT INTO ia_perguntas (user_id, questao_id, pergunta, resposta, created_at) VALUES (?,?,?,?,?)')
            ->execute([$uid, $ctxQ ? (int)$ctxQ['id'] : null, mb_substr($pergunta, 0, 1000), $resposta, now_str()]);
        jexit(['ok' => true, 'resposta' => $resposta, 'demo' => $demo, 'meta' => $meta]);
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
