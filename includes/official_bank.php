<?php
declare(strict_types=1);

/** Importa/atualiza o acervo oficial empacotado sem apagar usuários ou progresso. */
function official_bank_path(): string {
    return APP_ROOT . '/database/official_questions.json';
}

/** @return array{total:int,inserted:int,updated:int,skipped:int} */
function official_bank_sync(PDO $pdo): array {
    if (function_exists('papiro_ensure_runtime_schema')) papiro_ensure_runtime_schema($pdo);
    $path = official_bank_path();
    if (!is_file($path)) throw new RuntimeException('Arquivo database/official_questions.json não encontrado.');
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Não foi possível ler o banco oficial.');
    $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows)) throw new RuntimeException('Banco oficial inválido.');

    // Importante: um sync de conteúdo não pode apagar um gabarito que já tenha sido
    // validado pela IA ou pelo administrador. Gabarito oficial do pacote, quando houver,
    // continua tendo prioridade máxima.
    $find = $pdo->prepare('SELECT id,gabarito,gabarito_fonte,gabarito_confianca,gabarito_validado_em,resolucao FROM questoes WHERE slug = ? LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO questoes (slug,concurso,ano,numero,pagina,regiao,materia,assunto,dificuldade,enunciado,alt_a,alt_b,alt_c,alt_d,alt_e,gabarito,gabarito_fonte,gabarito_confianca,gabarito_validado_em,resolucao,origem,exibir_preview,ativo,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)');
    $upd = $pdo->prepare('UPDATE questoes SET concurso=?,ano=?,numero=?,pagina=?,regiao=?,materia=?,assunto=?,dificuldade=?,enunciado=?,alt_a=?,alt_b=?,alt_c=?,alt_d=?,alt_e=?,gabarito=?,gabarito_fonte=?,gabarito_confianca=?,gabarito_validado_em=?,resolucao=?,origem=?,exibir_preview=?,ativo=? WHERE id=?');

    $inserted = $updated = $skipped = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $q) {
            if (!is_array($q) || empty($q['slug'])) { $skipped++; continue; }
            $alts = array_values(array_pad(array_slice((array)($q['alternativas'] ?? []), 0, 5), 5, ''));
            $incomingGab = (int)($q['gabarito'] ?? -1);
            $incomingOfficial = $incomingGab >= 0 && $incomingGab <= 4;

            $find->execute([(string)$q['slug']]);
            $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;

            $incomingSource = trim((string)($q['gabarito_fonte'] ?? ''));
            $incomingConf = (float)($q['gabarito_confianca'] ?? 0);
            $gab = $incomingOfficial ? $incomingGab : -1;
            $fonte = $incomingOfficial ? ($incomingSource !== '' ? $incomingSource : 'oficial') : '';
            $conf = $incomingOfficial ? ($incomingConf > 0 ? max(0.0,min(1.0,$incomingConf)) : ($fonte === 'oficial' ? 1.0 : 0.99)) : 0.0;
            $validatedAt = $incomingOfficial ? date('Y-m-d H:i:s') : null;
            $resolucao = (string)($q['resolucao'] ?? '');

            if ($existing && !$incomingOfficial) {
                $oldGab = (int)($existing['gabarito'] ?? -1);
                if ($oldGab >= 0 && $oldGab <= 4) {
                    $gab = $oldGab;
                    $fonte = (string)($existing['gabarito_fonte'] ?? '') ?: 'banco';
                    $conf = (float)($existing['gabarito_confianca'] ?? 0);
                    $validatedAt = $existing['gabarito_validado_em'] ?: null;
                    // Preserva a explicação produzida na validação por IA.
                    if (str_starts_with($fonte, 'ia') && trim((string)($existing['resolucao'] ?? '')) !== '') {
                        $resolucao = (string)$existing['resolucao'];
                    }
                }
            }

            $vals = [
                (string)($q['concurso'] ?? 'Outro'), (int)($q['ano'] ?? 0), (int)($q['numero'] ?? 0),
                (int)($q['pagina'] ?? 0), (string)($q['regiao'] ?? ''), (string)($q['materia'] ?? 'Geral'),
                (string)($q['assunto'] ?? ''), (string)($q['dificuldade'] ?? 'Médio'), (string)($q['enunciado'] ?? ''),
                (string)$alts[0], (string)$alts[1], (string)$alts[2], (string)$alts[3], (string)$alts[4],
                $gab, $fonte, $conf, $validatedAt, $resolucao, (string)($q['origem'] ?? ''),
                (int)($q['exibir_preview'] ?? (!empty($q['visual_only']) ? 1 : 0)), (int)($q['ativo'] ?? 1),
            ];

            if ($existing) {
                $upd->execute([...$vals, (int)$existing['id']]);
                $updated++;
            } else {
                $ins->execute([(string)$q['slug'], ...$vals]);
                $inserted++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['total'=>count($rows),'inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped];
}

/** Sincroniza o banco oficial apenas quando a versão empacotada mudar. */
function official_bank_ensure_current(PDO $pdo, string $version = '2026.09-v251'): array {
    if (function_exists('papiro_ensure_runtime_schema')) papiro_ensure_runtime_schema($pdo);
    $current = '';
    try {
        $st = $pdo->prepare("SELECT meta_value FROM app_meta WHERE meta_key='official_bank_version' LIMIT 1");
        $st->execute();
        $current = (string)($st->fetchColumn() ?: '');
        $count = (int)$pdo->query("SELECT COUNT(*) FROM questoes WHERE slug LIKE 'oficial-%'")->fetchColumn();
        if ($current === $version && $count >= 3049) return ['total'=>$count,'inserted'=>0,'updated'=>0,'skipped'=>0,'cached'=>1];
    } catch (Throwable $e) {}

    $result = official_bank_sync($pdo);
    try {
        $pdo->prepare("DELETE FROM app_meta WHERE meta_key='official_bank_version'")->execute();
        $pdo->prepare('INSERT INTO app_meta (meta_key,meta_value) VALUES (?,?)')->execute(['official_bank_version',$version]);
    } catch (Throwable $e) {}
    $result['cached'] = 0;
    return $result;
}
