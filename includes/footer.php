    </main>
    <footer class="footer">
      <b>Papiro Máximo</b> v<?= e(APP_VERSION) ?> <span>Banco de questões e preparação militar</span>
    </footer>
  </div>

  <nav class="bottomnav">
    <a href="<?= e(url('index.php')) ?>" class="<?= ($active ?? '') === 'inicio' ? 'active' : '' ?>"><span>IN</span><small>Início</small></a>
    <a href="<?= e(url('questoes.php')) ?>" class="<?= ($active ?? '') === 'questoes' ? 'active' : '' ?>"><span>Q</span><small>Questões</small></a>
    <a href="<?= e(url('simulados.php')) ?>" class="<?= ($active ?? '') === 'simulados' ? 'active' : '' ?>"><span>SIM</span><small>Simulado</small></a>
    <a href="<?= e(url('ia.php')) ?>" class="<?= ($active ?? '') === 'ia' ? 'active' : '' ?>"><span>IA</span><small>Papiro IA</small></a>
    <a href="<?= e(url($user ? 'perfil.php' : 'login.php')) ?>" class="<?= ($active ?? '') === 'perfil' ? 'active' : '' ?>"><span>EU</span><small>Perfil</small></a>
  </nav>
</div>

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
