<?php
declare(strict_types=1);

/** Pequenas migrações compatíveis com instalações antigas do Papiro. */
function papiro_ensure_runtime_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Questões / respostas.
    try { $pdo->exec("ALTER TABLE questoes ADD COLUMN exibir_preview TINYINT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE questoes ADD COLUMN gabarito_fonte VARCHAR(24) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE questoes ADD COLUMN gabarito_confianca DECIMAL(5,4) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE questoes ADD COLUMN gabarito_validado_em DATETIME NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE respostas ADD COLUMN avaliavel TINYINT NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE respostas ADD COLUMN confianca VARCHAR(12) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}

    // Caderno de erros 2.5: causa + revisão espaçada.
    try { $pdo->exec("ALTER TABLE caderno_erros ADD COLUMN erro_tipo VARCHAR(24) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE caderno_erros ADD COLUMN proxima_revisao DATE NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE caderno_erros ADD COLUMN revisoes INT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE caderno_erros ADD COLUMN ultima_revisao DATETIME NULL"); } catch (Throwable $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS app_meta (meta_key VARCHAR(80) PRIMARY KEY, meta_value VARCHAR(255) NOT NULL)"); } catch (Throwable $e) {}

    // Tabelas novas com sintaxe compatível com o banco em uso.
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS metas_usuario (
            user_id INTEGER PRIMARY KEY,
            horas_semana REAL NOT NULL DEFAULT 10,
            questoes_semana INTEGER NOT NULL DEFAULT 150,
            simulados_semana INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS plano_diario (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            dia TEXT NOT NULL,
            tipo TEXT NOT NULL DEFAULT 'estudo',
            titulo TEXT NOT NULL,
            descricao TEXT NOT NULL DEFAULT '',
            link TEXT NOT NULL DEFAULT '',
            ordem INTEGER NOT NULL DEFAULT 0,
            concluido INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS simulados_execucoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            modo TEXT NOT NULL DEFAULT 'personalizado',
            concurso TEXT NOT NULL DEFAULT '',
            materia TEXT NOT NULL DEFAULT '',
            qtd INTEGER NOT NULL DEFAULT 0,
            ids_json TEXT NOT NULL,
            config_json TEXT NOT NULL DEFAULT '{}',
            inicio TEXT NOT NULL,
            fim TEXT NULL,
            respondidas INTEGER NOT NULL DEFAULT 0,
            avaliadas INTEGER NOT NULL DEFAULT 0,
            acertos INTEGER NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS simulado_respostas (
            execucao_id INTEGER NOT NULL,
            questao_id INTEGER NOT NULL,
            alternativa INTEGER NOT NULL,
            correta INTEGER NOT NULL DEFAULT 0,
            avaliavel INTEGER NOT NULL DEFAULT 1,
            tempo_seg INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NULL,
            PRIMARY KEY (execucao_id, questao_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS questao_denuncias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            questao_id INTEGER NOT NULL,
            tipo TEXT NOT NULL DEFAULT 'outro',
            detalhe TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'aberta',
            created_at TEXT NULL
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS metas_usuario (
            user_id INT PRIMARY KEY,
            horas_semana DECIMAL(5,1) NOT NULL DEFAULT 10,
            questoes_semana INT NOT NULL DEFAULT 150,
            simulados_semana INT NOT NULL DEFAULT 1,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS plano_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dia DATE NOT NULL,
            tipo VARCHAR(24) NOT NULL DEFAULT 'estudo',
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NOT NULL,
            link VARCHAR(255) NOT NULL DEFAULT '',
            ordem INT NOT NULL DEFAULT 0,
            concluido TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            UNIQUE KEY uq_plano_user_dia_ordem (user_id,dia,ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS simulados_execucoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            modo VARCHAR(24) NOT NULL DEFAULT 'personalizado',
            concurso VARCHAR(20) NOT NULL DEFAULT '',
            materia VARCHAR(50) NOT NULL DEFAULT '',
            qtd INT NOT NULL DEFAULT 0,
            ids_json LONGTEXT NOT NULL,
            config_json TEXT NOT NULL,
            inicio DATETIME NOT NULL,
            fim DATETIME NULL,
            respondidas INT NOT NULL DEFAULT 0,
            avaliadas INT NOT NULL DEFAULT 0,
            acertos INT NOT NULL DEFAULT 0,
            INDEX idx_sim_user_inicio (user_id,inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS simulado_respostas (
            execucao_id INT NOT NULL,
            questao_id INT NOT NULL,
            alternativa TINYINT NOT NULL,
            correta TINYINT NOT NULL DEFAULT 0,
            avaliavel TINYINT NOT NULL DEFAULT 1,
            tempo_seg INT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            PRIMARY KEY (execucao_id,questao_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS questao_denuncias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            questao_id INT NOT NULL,
            tipo VARCHAR(32) NOT NULL DEFAULT 'outro',
            detalhe TEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'aberta',
            created_at DATETIME NULL,
            INDEX idx_denuncias_status (status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Índices de leitura frequente. Ignora caso já existam.
    try { $pdo->exec("CREATE INDEX idx_questoes_filtros ON questoes (ativo, concurso, materia, ano)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_respostas_user_data ON respostas (user_id, created_at)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_caderno_revisao ON caderno_erros (user_id, proxima_revisao)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_plano_user_dia ON plano_diario (user_id, dia)"); } catch (Throwable $e) {}
}
