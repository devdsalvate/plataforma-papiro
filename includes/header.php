<?php
/* Papiro Máximo — topo do layout. Variáveis: $title, $active */
$user = current_user();
$nav = [
    ['inicio', 'Início', '🏠', 'index.php'],
    ['questoes', 'Questões', '📝', 'questoes.php'],
    ['trilhas', 'Trilhas', '🗺️', 'trilhas.php'],
    ['guia', 'Guia', '📖', 'guia.php'],
    ['ranking', 'Ranking', '🏆', 'ranking.php'],
    ['caderno', 'Caderno', '📓', 'caderno.php'],
    ['favoritos', 'Favoritos', '⭐', 'favoritos.php'],
    ['ia', 'IA', '🤖', 'ia.php'],
    ['videos', 'Videoaulas', '🎥', 'videoaulas.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Início') . ' · ' . APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" href="<?= e(url('icons/icon.svg')) ?>">
<meta name="theme-color" content="#0b1b33">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark">📜</span><span class="brand-name">Papiro <b>Máximo</b></span></a>
    <nav class="nav">
      <?php foreach ($nav as [$key, $label, $icon, $href]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>"><span><?= $icon ?></span> <?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user && is_admin($user)): ?>
        <a href="<?= e(url('admin/index.php')) ?>" class="<?= ($active ?? '') === 'admin' ? 'active' : '' ?>"><span>🛠️</span> Admin</a>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <?php if ($user): ?>
        <a class="user-chip" href="<?= e(url('perfil.php')) ?>">🎖️ <?= e($user['nome']) ?> <small>· 🔥 <?= (int)$user['ofensiva'] ?></small></a>
        <a class="logout" href="<?= e(url('logout.php')) ?>">Sair</a>
      <?php else: ?>
        <a class="btn btn-gold btn-block" href="<?= e(url('login.php')) ?>">Entrar</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('cadastro.php')) ?>">Criar conta</a>
      <?php endif; ?>
    </div>
  </aside>
  <div class="backdrop" id="backdrop"></div>

  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn only-mobile" id="menuBtn" aria-label="Menu">☰</button>
      <a class="brand-mini only-mobile" href="<?= e(url('index.php')) ?>">📜 <b>Papiro Máximo</b></a>
      <div class="top-right">
        <?php if ($user): ?>
          <span class="streak" title="Ofensiva">🔥 <?= (int)$user['ofensiva'] ?></span>
          <a class="avatar" href="<?= e(url('perfil.php')) ?>" title="<?= e($user['nome']) ?>"><?= e(mb_strtoupper(mb_substr($user['nome'], 0, 1))) ?></a>
        <?php else: ?>
          <a class="btn btn-small" href="<?= e(url('login.php')) ?>">Entrar</a>
          <a class="btn btn-gold btn-small" href="<?= e(url('cadastro.php')) ?>">Criar conta</a>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">
      <?php foreach (flashes() as $f): ?>
        <div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
