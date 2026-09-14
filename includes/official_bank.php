<?php
declare(strict_types=1);

/** Importa/atualiza o acervo oficial empacotado sem apagar usuários ou progresso. */
function official_bank_path(): string {
    return APP_ROOT . '/database/official_questions.json';
}

/** @return array{total:int,inserted:int,updated:int,skipped:int} */
function official_bank_sync(PDO $pdo): array {
    $path = official_bank_path();
    if (!is_file($path)) throw new RuntimeException('Arquivo database/official_questions.json não encontrado.');
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Não foi possível ler o banco oficial.');
    $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows)) throw new RuntimeException('Banco oficial inválido.');

    $find = $pdo->prepare('SELECT id FROM questoes WHERE slug = ? LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO questoes (slug,concurso,ano,numero,pagina,regiao,materia,assunto,dificuldade,enunciado,alt_a,alt_b,alt_c,alt_d,alt_e,gabarito,resolucao,origem,ativo,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)');
    $upd = $pdo->prepare('UPDATE questoes SET concurso=?,ano=?,numero=?,pagina=?,regiao=?,materia=?,assunto=?,dificuldade=?,enunciado=?,alt_a=?,alt_b=?,alt_c=?,alt_d=?,alt_e=?,gabarito=?,resolucao=?,origem=?,ativo=? WHERE id=?');

    $inserted = $updated = $skipped = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $q) {
            if (!is_array($q) || empty($q['slug'])) { $skipped++; continue; }
            $alts = array_values(array_pad(array_slice((array)($q['alternativas'] ?? []), 0, 5), 5, ''));
            $vals = [
                (string)($q['concurso'] ?? 'Outro'), (int)($q['ano'] ?? 0), (int)($q['numero'] ?? 0),
                (int)($q['pagina'] ?? 0), (string)($q['regiao'] ?? ''), (string)($q['materia'] ?? 'Geral'),
                (string)($q['assunto'] ?? ''), (string)($q['dificuldade'] ?? 'Médio'), (string)($q['enunciado'] ?? ''),
                (string)$alts[0], (string)$alts[1], (string)$alts[2], (string)$alts[3], (string)$alts[4],
                (int)($q['gabarito'] ?? -1), (string)($q['resolucao'] ?? ''), (string)($q['origem'] ?? ''), (int)($q['ativo'] ?? 1),
            ];
            $find->execute([(string)$q['slug']]);
            $id = $find->fetchColumn();
            if ($id) {
                $upd->execute([...$vals, (int)$id]);
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
