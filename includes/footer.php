    </main>
    <footer class="footer">
      <b>Papiro Máximo</b> v<?= e(APP_VERSION) ?> <span>Banco de questões e preparação militar</span>
    </footer>
  </div>

  <nav class="bottomnav">
    <a href="<?= e(url('index.php')) ?>" class="<?= ($active ?? '') === 'inicio' ? 'active' : '' ?>"><span>IN</span><small>Início</small></a>
    <a href="<?= e(url('questoes.php')) ?>" class="<?= ($active ?? '') === 'questoes' ? 'active' : '' ?>"><span>Q</span><small>Questões</small></a>
    <a href="<?= e(url('simulados.php')) ?>" class="<?= ($active ?? '') === 'simulados' ? 'active' : '' ?>"><span>SIM</span><small>Simulados</small></a>
    <a href="<?= e(url('trilhas.php')) ?>" class="<?= ($active ?? '') === 'trilhas' ? 'active' : '' ?>"><span>TR</span><small>Trilhas</small></a>
    <a href="<?= e(url('caderno.php')) ?>" class="<?= ($active ?? '') === 'caderno' ? 'active' : '' ?>"><span>CE</span><small>Caderno</small></a>
  </nav>
</div>

<script>
window.PM = {
  base: <?= json_encode(BASE_URL) ?>,
  csrf: <?= json_encode($user ? csrf_token() : '') ?>,
  uid: <?= $user ? (int)$user['id'] : 0 ?>
};
</script>
<script src="<?= e(url('assets/js/app.js?v=' . rawurlencode(APP_VERSION))) ?>"></script>
<?= $extra_js ?? '' ?>
</body>
</html>
