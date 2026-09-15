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
    'EFOMM' => 'EF', 'EPCAR' => 'EP', 'EEAR' => 'EE', 'CN' => 'CN', 'ITA' => 'IT',
    'ESA' => 'ES', 'EsPCEx' => 'EX', 'AFA' => 'AF', 'IME' => 'IM', 'EEAM' => 'EA', 'CFN' => 'CF', 'Outro' => 'OT',
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


function question_has_missing_context(array $q): bool {
    $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($q['enunciado'] ?? '')))));
    if ($text === '') return true;
    if ((int)($q['exibir_preview'] ?? 0) === 1) return false;
    $patterns = [
        '/de acordo com o texto/u',
        '/according to the text/i',
        '/choose the best alternative according to the text/i',
        '/choose the correct option according to the text/i',
        '/what happened .* according to the text/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text) && mb_strlen($text) < 220) return true;
    }
    return false;
}

function question_is_public_ready(array $q): bool {
    $alts = array_filter(q_alternativas($q), static fn($a) => trim((string)$a) !== '');
    if (count($alts) < 2) return false;
    return !question_has_missing_context($q);
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

    // Toda resposta conta como questão praticada. A taxa de acerto usa apenas
    // questões cujo gabarito foi validado (avaliavel = 1).
    $st = $pdo->prepare('SELECT COUNT(*) t, '
        . 'COALESCE(SUM(CASE WHEN avaliavel=1 THEN 1 ELSE 0 END),0) ta, '
        . 'COALESCE(SUM(CASE WHEN avaliavel=1 THEN correta ELSE 0 END),0) c, '
        . 'COUNT(DISTINCT questao_id) d FROM respostas WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch() ?: ['t'=>0,'ta'=>0,'c'=>0,'d'=>0];
    $tent = (int)$r['t'];
    $avaliadas = (int)$r['ta'];
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

    $st = $pdo->prepare('SELECT q.concurso c, COUNT(*) n, '
        . 'COALESCE(SUM(CASE WHEN r.avaliavel=1 THEN 1 ELSE 0 END),0) t, '
        . 'COALESCE(SUM(CASE WHEN r.avaliavel=1 THEN r.correta ELSE 0 END),0) ok '
        . 'FROM respostas r JOIN questoes q ON q.id = r.questao_id WHERE r.user_id = ? '
        . 'GROUP BY q.concurso ORDER BY n DESC');
    $st->execute([$uid]);
    $conc = $st->fetchAll();

    $st = $pdo->prepare('SELECT q.materia m, COUNT(DISTINCT r.questao_id) n, '
        . 'COALESCE(SUM(CASE WHEN r.avaliavel=1 THEN 1 ELSE 0 END),0) t, '
        . 'COALESCE(SUM(CASE WHEN r.avaliavel=1 THEN r.correta ELSE 0 END),0) ok '
        . 'FROM respostas r JOIN questoes q ON q.id = r.questao_id WHERE r.user_id = ? '
        . 'GROUP BY q.materia ORDER BY n DESC, q.materia ASC');
    $st->execute([$uid]);
    $materias = $st->fetchAll();

    $st = $pdo->prepare('SELECT ofensiva, foco FROM users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch() ?: ['ofensiva' => 0, 'foco' => 'EFOMM'];

    return [
        'segundos' => $segundos, 'tentativas' => $tent, 'avaliadas' => $avaliadas, 'acertos' => $ok,
        'distintas' => (int)$r['d'], 'taxa' => $avaliadas > 0 ? (int)round($ok * 100 / $avaliadas) : 0,
        'dias' => $dias, 'concursos' => $conc, 'materias' => $materias,
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
    // Contas internas (admin/gestor/suporte) nunca aparecem em ranking.
    $sql .= "WHERE u.role = 'aluno' ";
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

/* ============================================================
   Papiro 2.5 — domínio, metas, revisão espaçada e plano diário
   ============================================================ */

function week_bounds(): array {
    $monday = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $next = date('Y-m-d 00:00:00', strtotime('monday next week'));
    return [$monday, $next];
}

function user_goals(int $uid): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM metas_usuario WHERE user_id = ?');
    $st->execute([$uid]);
    $g = $st->fetch();
    if ($g) return $g;
    $pdo->prepare('INSERT INTO metas_usuario (user_id,horas_semana,questoes_semana,simulados_semana,updated_at) VALUES (?,?,?,?,?)')
        ->execute([$uid, 10, 150, 1, now_str()]);
    return ['user_id'=>$uid,'horas_semana'=>10,'questoes_semana'=>150,'simulados_semana'=>1,'updated_at'=>now_str()];
}

function user_week_progress(int $uid): array {
    [$ini,$fim] = week_bounds();
    $pdo = db();
    $st=$pdo->prepare('SELECT COALESCE(SUM(duracao_seg),0) FROM study_sessions WHERE user_id=? AND inicio>=? AND inicio<?');
    $st->execute([$uid,$ini,$fim]); $seg=(int)$st->fetchColumn();
    $st=$pdo->prepare('SELECT COUNT(*) FROM respostas WHERE user_id=? AND created_at>=? AND created_at<?');
    $st->execute([$uid,$ini,$fim]); $q=(int)$st->fetchColumn();
    $st=$pdo->prepare('SELECT COUNT(*) FROM simulados_execucoes WHERE user_id=? AND inicio>=? AND inicio<? AND respondidas>0');
    $st->execute([$uid,$ini,$fim]); $s=(int)$st->fetchColumn();
    return ['segundos'=>$seg,'horas'=>$seg/3600,'questoes'=>$q,'simulados'=>$s,'inicio'=>$ini,'fim'=>$fim];
}

function mastery_label(int $score, int $attempts): array {
    if ($attempts < 3) return ['status'=>'Começando','class'=>'start'];
    if ($score < 45) return ['status'=>'Reforçar base','class'=>'weak'];
    if ($score < 65) return ['status'=>'Aprendendo','class'=>'learn'];
    if ($score < 82) return ['status'=>'Praticando','class'=>'practice'];
    return ['status'=>'Dominado','class'=>'master'];
}

/** Domínio aproximado por matéria: precisão (75%) + volume de prática (25%). */
function user_mastery(int $uid): array {
    $pdo=db();
    $st=$pdo->prepare('SELECT q.materia, COUNT(*) tentativas, COUNT(DISTINCT r.questao_id) distintas, '
        .'COALESCE(SUM(r.correta),0) acertos FROM respostas r JOIN questoes q ON q.id=r.questao_id '
        .'WHERE r.user_id=? AND r.avaliavel=1 GROUP BY q.materia ORDER BY q.materia');
    $st->execute([$uid]);
    $rows=[];
    foreach($st->fetchAll() as $r){
        $t=(int)$r['tentativas']; $ok=(int)$r['acertos']; $d=(int)$r['distintas'];
        $acc=$t>0?$ok/$t:0; $volume=min(1,$d/40);
        $score=(int)round(($acc*.75+$volume*.25)*100);
        $lab=mastery_label($score,$t);
        $rows[]=['materia'=>(string)$r['materia'],'tentativas'=>$t,'distintas'=>$d,'acertos'=>$ok,'precisao'=>$t?round($ok*100/$t):0,'score'=>$score]+$lab;
    }
    return $rows;
}

/** Assuntos mais fracos já praticados, úteis para coach e simulado inteligente. */
function user_weak_topics(int $uid, int $limit=8): array {
    $pdo=db();
    $st=$pdo->prepare('SELECT q.materia,q.assunto,COUNT(*) t,COALESCE(SUM(r.correta),0) ok '
        .'FROM respostas r JOIN questoes q ON q.id=r.questao_id '
        ."WHERE r.user_id=? AND r.avaliavel=1 AND q.assunto<>'' GROUP BY q.materia,q.assunto HAVING COUNT(*)>=2 "
        .'ORDER BY (COALESCE(SUM(r.correta),0)/COUNT(*)) ASC, COUNT(*) DESC LIMIT '.max(1,$limit));
    $st->execute([$uid]);
    $out=[];
    foreach($st->fetchAll() as $r){$t=(int)$r['t'];$out[]=['materia'=>$r['materia'],'assunto'=>$r['assunto'],'tentativas'=>$t,'precisao'=>$t?(int)round((int)$r['ok']*100/$t):0];}
    return $out;
}

function review_due_count(int $uid, ?string $date=null): int {
    $date=$date?:today_str();
    $st=db()->prepare('SELECT COUNT(*) FROM caderno_erros WHERE user_id=? AND (proxima_revisao IS NULL OR proxima_revisao<=?) AND revisada=0');
    $st->execute([$uid,$date]);
    return (int)$st->fetchColumn();
}

function review_schedule_next(int $reviews): int {
    $steps=[1,3,7,15,30,60];
    return $steps[min(max(0,$reviews),count($steps)-1)];
}

/** Cria o plano apenas uma vez por dia. O aluno pode concluir itens sem perder o plano. */
function ensure_daily_plan(int $uid): array {
    $pdo=db(); $dia=today_str();
    $st=$pdo->prepare('SELECT * FROM plano_diario WHERE user_id=? AND dia=? ORDER BY ordem,id');
    $st->execute([$uid,$dia]); $existing=$st->fetchAll();
    if($existing) return $existing;

    $mastery=user_mastery($uid);
    usort($mastery,static fn($a,$b)=>$a['score']<=>$b['score']);
    $weak=$mastery[0]['materia']??'Matemática';
    $due=review_due_count($uid,$dia);
    $goals=user_goals($uid); $week=user_week_progress($uid);

    $uSt=$pdo->prepare('SELECT foco FROM users WHERE id=?');$uSt->execute([$uid]);$foco=(string)($uSt->fetchColumn()?:'EFOMM');
    $slug=strtolower($foco==='CN'?'cn':$foco);
    $st=$pdo->prepare('SELECT m.titulo,t.slug FROM trilha_modulos m JOIN trilhas t ON t.id=m.trilha_id '
        .'WHERE t.slug=? AND NOT EXISTS (SELECT 1 FROM trilha_progresso p WHERE p.user_id=? AND p.modulo_id=m.id) ORDER BY m.ordem LIMIT 1');
    $st->execute([$slug,$uid]); $next=$st->fetch();
    if(!$next){$st=$pdo->prepare("SELECT m.titulo,t.slug FROM trilha_modulos m JOIN trilhas t ON t.id=m.trilha_id WHERE t.slug='base' AND NOT EXISTS (SELECT 1 FROM trilha_progresso p WHERE p.user_id=? AND p.modulo_id=m.id) ORDER BY m.ordem LIMIT 1");$st->execute([$uid]);$next=$st->fetch();}

    $items=[];
    $items[]=['teoria','Bloco principal: '.$weak,'45–60 min de teoria ativa. Feche o material e explique de memória antes de avançar.','trilhas.php',1];
    $items[]=['questoes','Bateria direcionada: '.$weak,'Resolva 15–20 questões. Classifique cada erro por causa e confiança.','questoes.php?materia='.urlencode($weak).'&status=nao_resolvidas',2];
    if($due>0) $items[]=['revisao','Revisões vencendo hoje',$due.' item(ns) do caderno de erros estão prontos para revisão espaçada.','caderno.php?rev=hoje',3];
    else $items[]=['revisao','Revisão curta','Revise 10–15 minutos de erros recentes ou fórmulas que ainda exigem consulta.','caderno.php',3];
    if($next) $items[]=['trilha','Avance na trilha','Próximo módulo: '.$next['titulo'].'. Só conclua ao atingir o critério de domínio.','trilha.php?slug='.urlencode((string)$next['slug']),4];
    else $items[]=['trilha','Consolidação','Escolha um módulo da trilha para consolidar com questões mistas.','trilhas.php',4];
    if((int)$week['simulados'] < (int)$goals['simulados_semana']) $items[]=['simulado','Simulado da semana','Sua meta semanal ainda pede '.max(1,(int)$goals['simulados_semana']-(int)$week['simulados']).' simulado(s). Use o modo inteligente para atacar pontos fracos.','simulados.php?modo=inteligente',5];

    $ins=$pdo->prepare('INSERT INTO plano_diario (user_id,dia,tipo,titulo,descricao,link,ordem,concluido,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach($items as $x)$ins->execute([$uid,$dia,$x[0],$x[1],$x[2],$x[3],$x[4],0,now_str()]);
    $st->execute([$uid,$dia]);
    return $st->fetchAll();
}

function coach_snapshot(int $uid): string {
    $stats=user_stats($uid); $mastery=user_mastery($uid); $weak=user_weak_topics($uid,5); $due=review_due_count($uid); $week=user_week_progress($uid); $goals=user_goals($uid);
    $parts=[];
    $parts[]='Foco: '.$stats['foco'].'; precisão validada: '.$stats['taxa'].'%; questões distintas: '.$stats['distintas'].'; ofensiva: '.$stats['ofensiva'].' dias.';
    $parts[]='Semana: '.round($week['horas'],1).'/'.$goals['horas_semana'].' h; '.$week['questoes'].'/'.$goals['questoes_semana'].' questões; '.$week['simulados'].'/'.$goals['simulados_semana'].' simulados.';
    if($mastery){$m=array_map(static fn($x)=>$x['materia'].' '.$x['score'].'% ('.$x['status'].')',array_slice($mastery,0,8));$parts[]='Domínio por matéria: '.implode(', ',$m).'.';}
    if($weak){$w=array_map(static fn($x)=>$x['materia'].'/'.$x['assunto'].' '.$x['precisao'].'%', $weak);$parts[]='Pontos fracos observados: '.implode(', ',$w).'.';}
    $parts[]='Revisões vencendo: '.$due.'.';
    return implode("\n",$parts);
}
