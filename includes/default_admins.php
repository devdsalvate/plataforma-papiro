<?php
declare(strict_types=1);

/**
 * Contas administrativas iniciais do Papiro Máximo.
 * As senhas podem (e devem) ser alteradas em Meu perfil depois do primeiro login.
 */
function papiro_default_admin_accounts(): array {
    return [
        ['nome' => 'Administrador Geral', 'email' => 'admin@papiromaximo.local', 'senha' => 'PapiroADM@2026', 'foco' => 'EFOMM'],
        ['nome' => 'Gestor Papiro', 'email' => 'gestor@papiromaximo.local', 'senha' => 'GestorPM@2026', 'foco' => 'ITA'],
        ['nome' => 'Suporte Papiro', 'email' => 'suporte@papiromaximo.local', 'senha' => 'SuportePM@2026', 'foco' => 'AFA'],
    ];
}

/** Cria as contas padrão se ainda não existirem e garante que permaneçam como admin. */
function ensure_default_admins(PDO $pdo): array {
    $find = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO users (nome,email,senha_hash,foco,role) VALUES (?,?,?,?,?)');
    $promote = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $created = $existing = 0;

    foreach (papiro_default_admin_accounts() as $account) {
        $email = strtolower(trim((string)$account['email']));
        $find->execute([$email]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (($row['role'] ?? '') !== 'admin') $promote->execute([(int)$row['id']]);
            $existing++;
            continue;
        }
        $insert->execute([
            (string)$account['nome'],
            $email,
            password_hash((string)$account['senha'], PASSWORD_DEFAULT),
            (string)$account['foco'],
            'admin',
        ]);
        $created++;
    }
    return ['created'=>$created, 'existing'=>$existing, 'total'=>count(papiro_default_admin_accounts())];
}

/** Remove de instalações antigas a estrutura de recuperação local de senha. */
function remove_password_recovery_storage(PDO $pdo): void {
    try { $pdo->exec('DROP TABLE IF EXISTS password_resets'); } catch (Throwable $e) { /* não bloqueia atualização */ }
}
