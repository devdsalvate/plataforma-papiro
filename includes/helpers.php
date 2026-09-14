<?php
declare(strict_types=1);
/* Papiro Máximo — Helpers, constantes, estatísticas */

/* Polyfill mb_* (hosts sem ext-mbstring) */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return strlen((string)$s); }
    function mb_substr($s, $start, $len = null, $enc = null) {
        $arr = preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
        if ($arr === false) return substr((string)$s, (int)$start, $len === null ? 2147483647 : (int)$len);
        return implode('', array_slice($arr, (int)$start, $len));
    }
    function mb_strtoupper($s, $enc = null) { return strtoupper((string)$s); }
}

const CONCURSOS_FOCO = ['EFOMM', 'EPCAR', 'EEAR', 'CN', 'ITA'];
const CONCURSOS_TODOS = ['EFOMM', 'EPCAR', 'EEAR', 'CN', 'ITA', 'ESA', 'EsPCEx', 'AFA', 'IME', 'EEAM', 'CFN', 'Outro'];
const CONCURSO_ICONS = [
    'EFOMM' => '⚓', 'EPCAR' => '✈️', 'EEAR' => '🛩️', 'CN' => '🧭', 'ITA' => '🚀',
    'ESA' => '🎖️', 'EsPCEx' => '🏅', 'AFA' => '🛫', 'IME' => '⚙️', 'EEAM' => '⚓', 'CFN' => '🪖', 'Outro' => '📝',
];
const CONCURSO_NOMES = [
    'EFOMM' => 'EFOMM', 'EPCAR' => 'EPCAR', 'EEAR' => 'EEAR', 'CN' => 'Colégio Naval',
    'ITA' => 'ITA', 'ESA' => 'ESA', 'EsPCEx' => 'EsPCEx', 'AFA' => 'AFA',
    'IME' => 'IME', 'EEAM' => 'EEAM', 'CFN' => 'CFN', 'Outro' => 'Outros',
];
const MATERIAS = ['Matemática', 'Física', 'Química', 'Português', 'Inglês', 'Ciências', 'Estudos Sociais', 'História/Geografia', 'Geral'];
const DIFICULDADES = ['Fácil', 'Médio', 'Difícil'];
const LETRAS = ['A', 'B', 'C', 'D', 'E'];

function concurso_nome(string $c): string { return CONCURSO_NOMES[$c] ?? $c; }
function concurso_icone(string $c): string { return CONCURSO_ICONS[$c] ?? '📝'; }

function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

/** @return array flashes e limpa */
function flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function fmt_duracao(int $seg): string {
    $seg = max(0, $seg);
    if ($seg < 60) return $seg . 's';
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    if ($h > 0) return $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
    return $m . 'min';
}

function fmt_data(?string $dt): string {
    if (!$dt) return '—';
    $t = strtotime($dt);
    return $t ? date('d/m/Y H:i', $t) : $dt;
}

function q_alternativas(array $q): array {
    return [(string)$q['alt_a'], (string)$q['alt_b'], (string)$q['alt_c'], (string)$q['alt_d'], (string)$q['alt_e']];
}

/** Atualiza ofensiva (streak) do usuário. Chamar ao registrar atividade. */
function touch_atividade(int $uid): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT ofensiva, ultima_atividade FROM users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u) return;
    $hoje = today_str();
    if (($u['ultima_atividade'] ?? '') === $hoje) return;
    $ontem = date('Y-m-d', strtotime('-1 day'));
    $of = (($u['ultima_atividade'] ?? '') === $ontem) ? ((int)$u['ofensiva'] + 1) : 1;
    $pdo->prepare('UPDATE users SET ofensiva = ?, ultima_atividade = ? WHERE id = ?')->execute([$of, $hoje, $uid]);
}

/** Estatísticas completas do usuário p/ o dashboard. */
function user_stats(int $uid): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT COALESCE(SUM(duracao_seg),0) FROM study_sessions WHERE user_id = ?');
    $st->execute([$uid]);
    $segundos = (int)$st->fetchColumn();

    $st = $pdo->prepare('SELECT COUNT(*) t, COALESCE(SUM(correta),0) c, COUNT(DISTINCT questao_id) d FROM respostas WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch();
    $tent = (int)$r['t'];
    $ok = (int)$r['c'];

    $dias = [];
    for ($i = 29; $i >= 0; $i--) $dias[date('Y-m-d', strtotime("-$i day"))] = ['q' => 0, 's' => 0];
    $corte = date('Y-m-d 00:00:00', strtotime('-29 days'));
    $st = $pdo->prepare('SELECT created_at FROM respostas WHERE user_id = ? AND created_at >= ?');
    $st->execute([$uid, $corte]);
    while ($row = $st->fetch()) {
        $d = substr((string)$row['created_at'], 0, 10);
        if (isset($dias[$d])) $dias[$d]['q']++;
    }
    $st = $pdo->prepare('SELECT inicio, duracao_seg FROM study_sessions WHERE user_id = ? AND inicio >= ?');
    $st->execute([$uid, $corte]);
    while ($row = $st->fetch()) {
        $d = substr((string)$row['inicio'], 0, 10);
        if (isset($dias[$d])) $dias[$d]['s'] += (int)$row['duracao_seg'];
    }

    $st = $pdo->prepare('SELECT q.concurso c, COUNT(*) t, COALESCE(SUM(r.correta),0) ok FROM respostas r JOIN questoes q ON q.id = r.questao_id WHERE r.user_id = ? GROUP BY q.concurso ORDER BY t DESC');
    $st->execute([$uid]);
    $conc = $st->fetchAll();

    $st = $pdo->prepare('SELECT ofensiva, foco FROM users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch() ?: ['ofensiva' => 0, 'foco' => 'EFOMM'];

    return [
        'segundos' => $segundos, 'tentativas' => $tent, 'acertos' => $ok,
        'distintas' => (int)$r['d'], 'taxa' => $tent > 0 ? (int)round($ok * 100 / $tent) : 0,
        'dias' => $dias, 'concursos' => $conc,
        'ofensiva' => (int)$u['ofensiva'], 'foco' => (string)$u['foco'],
    ];
}

/** Ranking por período. $desde/$ate no formato 'Y-m-d H:i:s'. */
function ranking_rows(string $desde, string $ate, ?int $grupo_id = null, int $limit = 20): array {
    $pdo = db();
    $params = [$desde, $ate, $desde, $ate];
    $sql = 'SELECT u.id, u.nome, u.foco, u.ofensiva, '
        . 'COALESCE((SELECT SUM(duracao_seg) FROM study_sessions s WHERE s.user_id = u.id AND s.inicio >= ? AND s.inicio < ?),0) AS seg, '
        . 'COALESCE((SELECT COUNT(*) FROM respostas r WHERE r.user_id = u.id AND r.created_at >= ? AND r.created_at < ?),0) AS q '
        . 'FROM users u ';
    if ($grupo_id) {
        $sql .= 'JOIN grupo_membros gm ON gm.user_id = u.id AND gm.grupo_id = ? ';
        $params[] = $grupo_id;
    }
    $sql .= 'ORDER BY seg DESC, q DESC LIMIT ' . max(1, (int)$limit);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Progresso do usuário numa trilha: [total, feitos, pct]. */
function trilha_progresso(int $uid, int $trilha_id): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT COUNT(*) FROM trilha_modulos WHERE trilha_id = ?');
    $st->execute([$trilha_id]);
    $total = (int)$st->fetchColumn();
    if ($total === 0) return [0, 0, 0];
    $st = $pdo->prepare('SELECT COUNT(*) FROM trilha_progresso p JOIN trilha_modulos m ON m.id = p.modulo_id WHERE p.user_id = ? AND m.trilha_id = ?');
    $st->execute([$uid, $trilha_id]);
    $feitos = (int)$st->fetchColumn();
    return [$total, $feitos, (int)round($feitos * 100 / $total)];
}

/** Última resposta do usuário p/ cada questão (mapa questao_id => row). */
function user_answers_map(int $uid, array $qids): array {
    if (!$qids) return [];
    $in = implode(',', array_fill(0, count($qids), '?'));
    // Compatível MySQL/SQLite: pega a mais recente por questão via NOT EXISTS
    $sql = "SELECT r.* FROM respostas r WHERE r.user_id = ? AND r.questao_id IN ($in) "
        . 'AND NOT EXISTS (SELECT 1 FROM respostas r2 WHERE r2.user_id = r.user_id AND r2.questao_id = r.questao_id AND r2.id > r.id)';
    $st = db()->prepare($sql);
    $st->execute(array_merge([$uid], array_values($qids)));
    $map = [];
    foreach ($st->fetchAll() as $row) $map[(int)$row['questao_id']] = $row;
    return $map;
}
