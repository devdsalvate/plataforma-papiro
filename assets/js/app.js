/* Papiro Máximo — interações (fetch API + timer + gráfico) */
(function () {
  'use strict';
  var PM = window.PM || { base: '', csrf: '', uid: 0 };
  function apiUrl() { return (PM.base || '') + '/api.php'; }

  function api(action, data) {
    data = data || {};
    data.action = action;
    data.csrf = PM.csrf;
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    return fetch(apiUrl(), { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  /* ---- menu mobile / flashes ---- */
  var menuBtn = document.getElementById('menuBtn');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('backdrop');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show', sidebar.classList.contains('open'));
    });
    if (backdrop) backdrop.addEventListener('click', function () {
      sidebar.classList.remove('open'); backdrop.classList.remove('show');
    });
  }
  setTimeout(function () {
    document.querySelectorAll('.flash').forEach(function (el) { el.style.display = 'none'; });
  }, 6000);
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* ---- cronômetro de estudos ---- */
  var tDisplay = document.getElementById('timerDisplay');
  if (tDisplay) {
    var btnStart = document.getElementById('timerStart');
    var btnStop = document.getElementById('timerStop');
    var tStatus = document.getElementById('timerStatus');
    var tick = null;
    function fmt(s) {
      s = Math.max(0, Math.floor(s));
      var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
      function p(n) { return (n < 10 ? '0' : '') + n; }
      return p(h) + ':' + p(m) + ':' + p(ss);
    }
    function render() {
      var t0 = parseInt(localStorage.getItem('pm_timer_start') || '0', 10);
      if (t0 > 0) {
        tDisplay.textContent = fmt((Date.now() - t0) / 1000);
        if (btnStart) btnStart.disabled = true;
        if (btnStop) btnStop.disabled = false;
        if (tStatus) tStatus.textContent = '⏺️ estudando...';
      } else {
        if (btnStart) btnStart.disabled = false;
        if (btnStop) btnStop.disabled = true;
        if (tStatus) tStatus.textContent = 'Timer parado. Bora manter a ofensiva! 🔥';
      }
    }
    if (btnStart) btnStart.addEventListener('click', function () {
      api('sessao_inicio', {}).then(function (res) {
        if (!res.ok) { alert(res.error || 'Erro ao iniciar.'); return; }
        localStorage.setItem('pm_timer_start', String(Date.now()));
        localStorage.setItem('pm_sessao_id', String(res.id));
        render();
      });
    });
    if (btnStop) btnStop.addEventListener('click', function () {
      var t0 = parseInt(localStorage.getItem('pm_timer_start') || '0', 10);
      var sid = localStorage.getItem('pm_sessao_id') || '';
      var seg = t0 > 0 ? Math.floor((Date.now() - t0) / 1000) : 0;
      api('sessao_fim', { id: sid, seg: seg }).then(function (res) {
        localStorage.removeItem('pm_timer_start');
        localStorage.removeItem('pm_sessao_id');
        tDisplay.textContent = '00:00:00';
        render();
        if (res.ok) location.reload();
        else alert(res.error || 'Erro ao encerrar.');
      });
    });
    tick = setInterval(render, 1000);
    render();
  }

  /* ---- gráfico do dashboard ---- */
  var chart = document.getElementById('chartMain');
  if (chart && chart.getContext) {
    var raw = JSON.parse(chart.getAttribute('data-chart') || '{}');
    var labels = Object.keys(raw);
    function draw(days) {
      var ctx = chart.getContext('2d');
      var W = chart.width = chart.offsetWidth * 2;
      var H = chart.height = 440;
      ctx.clearRect(0, 0, W, H);
      var slice = labels.slice(-days);
      var maxQ = 1, maxH = 1;
      slice.forEach(function (d) {
        maxQ = Math.max(maxQ, raw[d].q);
        maxH = Math.max(maxH, raw[d].s / 3600);
      });
      var padL = 70, padB = 50, padT = 20;
      var cw = (W - padL - 20) / slice.length;
      ctx.font = '22px sans-serif';
      // eixo Y
      for (var g = 0; g <= 4; g++) {
        var y = padT + (H - padT - padB) * g / 4;
        ctx.strokeStyle = '#e5ddc9'; ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(W - 10, y); ctx.stroke();
        ctx.fillStyle = '#6f6a5c'; ctx.textAlign = 'right';
        ctx.fillText(String(Math.round(maxQ * (4 - g) / 4)), padL - 8, y + 7);
      }
      // barras (questões) + linha (horas)
      var pts = [];
      slice.forEach(function (d, i) {
        var q = raw[d].q, h = raw[d].s / 3600;
        var bh = (H - padT - padB) * (q / maxQ);
        var x = padL + i * cw + cw * 0.2, w = cw * 0.6;
        ctx.fillStyle = '#e3a82b';
        ctx.fillRect(x, H - padB - bh, w, bh);
        pts.push([x + w / 2, H - padB - (H - padT - padB) * (h / maxH)]);
        if (days <= 7 || i % 5 === 0 || i === slice.length - 1) {
          ctx.fillStyle = '#6f6a5c'; ctx.textAlign = 'center';
          ctx.fillText(d.slice(8, 10) + '/' + d.slice(5, 7), x + w / 2, H - 15);
        }
      });
      ctx.strokeStyle = '#0b1b33'; ctx.lineWidth = 4; ctx.beginPath();
      pts.forEach(function (p, i) { i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]); });
      ctx.stroke();
      ctx.fillStyle = '#0b1b33';
      pts.forEach(function (p) { ctx.beginPath(); ctx.arc(p[0], p[1], 6, 0, 7); ctx.fill(); });
    }
    draw(7);
    document.querySelectorAll('[data-days]').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('[data-days]').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        draw(parseInt(b.getAttribute('data-days'), 10));
      });
    });
  }

  /* ---- resolver questão ---- */
  var qBox = document.getElementById('questaoBox');
  if (qBox) {
    var qid = qBox.getAttribute('data-qid');
    var t0q = Date.now();
    var answered = qBox.getAttribute('data-answered') === '1';
    var sel = null;
    qBox.querySelectorAll('.alt').forEach(function (el) {
      el.addEventListener('click', function () {
        if (answered) return;
        qBox.querySelectorAll('.alt').forEach(function (x) { x.classList.remove('selected'); });
        el.classList.add('selected');
        sel = el.getAttribute('data-alt');
        var btn = document.getElementById('btnResponder');
        if (btn) btn.disabled = false;
      });
    });
    var btnResp = document.getElementById('btnResponder');
    if (btnResp) btnResp.addEventListener('click', function () {
      if (sel === null || answered) return;
      btnResp.disabled = true;
      btnResp.innerHTML = '<span class="spin">⏳</span> Corrigindo...';
      api('responder', { qid: qid, alt: sel, tempo: Math.floor((Date.now() - t0q) / 1000) }).then(function (res) {
        if (!res.ok) { alert(res.error || 'Erro.'); btnResp.disabled = false; btnResp.textContent = 'Responder'; return; }
        answered = true;
        var fb = document.getElementById('feedback');
        qBox.querySelectorAll('.alt').forEach(function (x) {
          var a = parseInt(x.getAttribute('data-alt'), 10);
          if (a === res.gabarito) x.classList.add('certa');
          if (a === parseInt(sel, 10) && !res.correta) x.classList.add('errada');
        });
        if (fb) {
          fb.className = 'feedback ' + (res.correta ? 'ok' : 'no');
          fb.textContent = res.correta ? '✅ Correta! Gabarito: letra ' + res.letra : '❌ Incorreta. Gabarito: letra ' + res.letra;
        }
        var rb = document.getElementById('resolucaoBox');
        if (rb) { rb.style.display = ''; rb.querySelector('.res-body').innerHTML = res.resolucao; }
        var nb = document.getElementById('nextBox');
        if (nb) nb.style.display = '';
        btnResp.style.display = 'none';
      });
    });
    // IA explica esta questão
    var btnIa = document.getElementById('btnIaQuestao');
    if (btnIa) btnIa.addEventListener('click', function () {
      var box = document.getElementById('iaQuestaoBox');
      btnIa.disabled = true;
      if (box) { box.style.display = ''; box.innerHTML = '🤖 <i>Papiro IA pensando...</i>'; }
      api('ia', { questao_id: qid, pergunta: 'Explique esta questão passo a passo.' }).then(function (res) {
        btnIa.disabled = false;
        if (box) box.innerHTML = res.ok ? ('<b>🤖 Papiro IA:</b><br>' + res.resposta) : ('⚠️ ' + (res.error || 'Erro.'));
      });
    });
  }

  /* ---- favoritos ---- */
  document.querySelectorAll('[data-fav]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      api('favorito', { qid: btn.getAttribute('data-fav') }).then(function (res) {
        if (!res.ok) { alert(res.error || 'Erro.'); return; }
        btn.classList.toggle('faved', !!res.fav);
        btn.innerHTML = res.fav ? '⭐ Favoritada' : '☆ Favoritar';
        if (!res.fav && btn.hasAttribute('data-remove-row')) {
          var row = btn.closest('[data-fav-row]');
          if (row) row.remove();
        }
      });
    });
  });

  /* ---- comentários ---- */
  var cForm = document.getElementById('commentForm');
  if (cForm) cForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var ta = document.getElementById('commentText');
    var txt = (ta.value || '').trim();
    if (txt.length < 2) return;
    api('comentario', { qid: cForm.getAttribute('data-qid'), texto: txt }).then(function (res) {
      if (!res.ok) { alert(res.error || 'Erro.'); return; }
      ta.value = '';
      var list = document.getElementById('commentList');
      if (list) {
        var d = document.createElement('div');
        d.className = 'comment';
        d.innerHTML = '<span class="who"></span><span class="when">agora</span><div></div>';
        d.querySelector('.who').textContent = res.nome;
        d.querySelector('div').textContent = txt;
        list.prepend(d);
        var empty = document.getElementById('noComments');
        if (empty) empty.remove();
      }
    });
  });

  /* ---- trilhas: marcar módulo ---- */
  document.querySelectorAll('[data-modulo]').forEach(function (chk) {
    chk.addEventListener('change', function () {
      api('trilha_toggle', { modulo: chk.getAttribute('data-modulo') }).then(function (res) {
        if (!res.ok) { alert(res.error || 'Erro.'); chk.checked = !chk.checked; return; }
        var row = chk.closest('.modulo');
        if (row) row.classList.toggle('done', !!res.done);
        var bar = document.getElementById('trilhaBar');
        var pct = document.getElementById('trilhaPct');
        if (bar && typeof res.pct !== 'undefined') bar.style.width = res.pct + '%';
        if (pct && typeof res.pct !== 'undefined') pct.textContent = res.pct + '%';
      });
    });
  });

  /* ---- caderno de erros ---- */
  document.querySelectorAll('[data-save-note]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var qid2 = btn.getAttribute('data-save-note');
      var ta = document.querySelector('textarea[data-note="' + qid2 + '"]');
      api('caderno_salvar', { qid: qid2, anotacao: ta ? ta.value : '' }).then(function (res) {
        btn.textContent = res.ok ? '✅ Salvo!' : '⚠️ Erro';
        setTimeout(function () { btn.textContent = '💾 Salvar anotação'; }, 2000);
      });
    });
  });
  document.querySelectorAll('[data-revisada]').forEach(function (chk) {
    chk.addEventListener('change', function () {
      api('caderno_revisada', { qid: chk.getAttribute('data-revisada'), v: chk.checked ? 1 : 0 });
      var row = chk.closest('.q-item');
      if (row) row.style.opacity = chk.checked ? '.65' : '1';
    });
  });
  document.querySelectorAll('[data-remove-err]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Remover esta questão do caderno de erros?')) return;
      api('caderno_remover', { qid: btn.getAttribute('data-remove-err') }).then(function (res) {
        if (res.ok) { var row = btn.closest('[data-err-row]'); if (row) row.remove(); }
      });
    });
  });

  /* ---- chat IA (página ia.php) ---- */
  var iaForm = document.getElementById('iaForm');
  if (iaForm) iaForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var inp = document.getElementById('iaInput');
    var txt = (inp.value || '').trim();
    if (!txt) return;
    var log = document.getElementById('iaLog');
    var u = document.createElement('div');
    u.className = 'msg user'; u.textContent = txt; log.appendChild(u);
    var w = document.createElement('div');
    w.className = 'msg ia'; w.innerHTML = '<i>🤖 pensando...</i>'; log.appendChild(w);
    log.scrollTop = log.scrollHeight;
    inp.value = '';
    api('ia', { pergunta: txt, questao_id: iaForm.getAttribute('data-qid') || '' }).then(function (res) {
      w.innerHTML = res.ok ? res.resposta : ('⚠️ ' + (res.error || 'Erro.'));
      log.scrollTop = log.scrollHeight;
    });
  });

  /* ---- PWA ---- */
  if ('serviceWorker' in navigator && /^https?:$/.test(location.protocol)) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register((PM.base || '') + '/sw.js').catch(function () {});
    });
  }
})();
