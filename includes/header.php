<?php
/* Papiro Máximo — layout v2.5.1 */
$user = current_user();
$nav = [
    ['inicio', 'Início', 'IN', 'index.php'],
    ['questoes', 'Questões', 'Q', 'questoes.php'],
    ['simulados', 'Simulados', 'SIM', 'simulados.php'],
    ['trilhas', 'Trilhas', 'TR', 'trilhas.php'],
    ['guia', 'Guia', 'G', 'guia.php'],
    ['ranking', 'Ranking', 'R', 'ranking.php'],
    ['caderno', 'Caderno de erros', 'CE', 'caderno.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Início') . ' · ' . APP_NAME) ?></title>
<script>
(function(){try{var t=localStorage.getItem('pm_theme')||'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . rawurlencode(APP_VERSION))) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/papiro-v2.css?v=' . rawurlencode(APP_VERSION))) ?>">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" href="<?= e(url('icons/papiro-logo.svg')) ?>">
<meta name="theme-color" content="#103b72">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <img class="brand-logo" src="<?= e(url('icons/papiro-logo.svg')) ?>" alt="Papiro Máximo">
      <span class="brand-name">Papiro <b>Máximo</b><small>estudo militar</small></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as [$key, $label, $icon, $href]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>"><span class="nav-glyph"><?= e($icon) ?></span><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
      <?php if ($user && is_admin($user)): ?>
        <a href="<?= e(url('admin/index.php')) ?>" class="<?= ($active ?? '') === 'admin' ? 'active' : '' ?>"><span class="nav-glyph">AD</span><span>Admin</span></a>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <?php if ($user): ?>
        <a class="user-chip" href="<?= e(url('perfil.php')) ?>"><span class="mini-avatar"><?= e(mb_strtoupper(mb_substr($user['nome'],0,1))) ?></span><span><?= e($user['nome']) ?><small><?= (int)$user['ofensiva'] ?> dias de ofensiva</small></span></a>
        <div class="side-foot-actions">
          <a class="btn btn-ghost btn-block btn-small" href="<?= e(url('ia.php')) ?>">Tutor IA</a>
          <a class="logout" href="<?= e(url('logout.php')) ?>">Sair</a>
        </div>
      <?php else: ?>
        <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>">Entrar</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('cadastro.php')) ?>">Criar conta</a>
      <?php endif; ?>
    </div>
  </aside>
  <div class="backdrop" id="backdrop"></div>

  <div class="main-col">
    <header class="topbar">
      <div class="topbar-left">
        <button class="icon-btn only-mobile" id="menuBtn" aria-label="Abrir menu">☰</button>
        <a class="brand-mini only-mobile" href="<?= e(url('index.php')) ?>"><b>Papiro Máximo</b></a>
        <span class="top-context"><?= e($title ?? 'Início') ?></span>
      </div>
      <div class="top-right">
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema"><span class="theme-dot"></span><span id="themeLabel">Tema</span></button>
        <?php if ($user): ?>
          <span class="streak" title="Ofensiva"><?= (int)$user['ofensiva'] ?> dias</span>
          <a class="avatar" href="<?= e(url('perfil.php')) ?>" title="<?= e($user['nome']) ?>"><?= e(mb_strtoupper(mb_substr($user['nome'], 0, 1))) ?></a>
        <?php else: ?>
          <a class="btn btn-small" href="<?= e(url('login.php')) ?>">Entrar</a>
          <a class="btn btn-primary btn-small" href="<?= e(url('cadastro.php')) ?>">Criar conta</a>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">
      <?php foreach (flashes() as $f): ?>
        <div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
