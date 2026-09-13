    </main>
    <footer class="footer">
      📜 <b>Papiro Máximo</b> v<?= e(APP_VERSION) ?> · ⚓ EFOMM · ✈️ EPCAR · 🛩️ EEAR · 🧭 Colégio Naval · 🚀 ITA
    </footer>
  </div><!-- /main-col -->

  <nav class="bottomnav">
    <a href="<?= e(url('index.php')) ?>" class="<?= ($active ?? '') === 'inicio' ? 'active' : '' ?>">🏠<small>Início</small></a>
    <a href="<?= e(url('questoes.php')) ?>" class="<?= ($active ?? '') === 'questoes' ? 'active' : '' ?>">📝<small>Questões</small></a>
    <a href="<?= e(url('trilhas.php')) ?>" class="<?= ($active ?? '') === 'trilhas' ? 'active' : '' ?>">🗺️<small>Trilhas</small></a>
    <a href="<?= e(url('ranking.php')) ?>" class="<?= ($active ?? '') === 'ranking' ? 'active' : '' ?>">🏆<small>Ranking</small></a>
    <a href="<?= e(url($user ? 'perfil.php' : 'login.php')) ?>" class="<?= ($active ?? '') === 'perfil' ? 'active' : '' ?>">👤<small>Perfil</small></a>
  </nav>
</div><!-- /shell -->

<script>
window.PM = {
  base: <?= json_encode(BASE_URL) ?>,
  csrf: <?= json_encode($user ? csrf_token() : '') ?>,
  uid: <?= $user ? (int)$user['id'] : 0 ?>
};
</script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?= $extra_js ?? '' ?>
</body>
</html>
