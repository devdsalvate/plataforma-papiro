/* Papiro Máximo — interações (fetch API + timer + gráfico) */
(function () {
  'use strict';
  var PM = window.PM || { base: '', csrf: '', uid: 0 };
  function apiUrl() { return (PM.base || '') + '/api.php'; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  function api(action, data) {
    data = data || {};
    data.action = action;
    data.csrf = PM.csrf;
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    var ctl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = ctl ? setTimeout(function(){ ctl.abort(); }, action === 'ia' ? 26000 : 18000) : null;
    return fetch(apiUrl(), { method: 'POST', body: body, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
      .then(function (r) { return r.json(); })
      .catch(function (e) { return { ok:false, error: e && e.name === 'AbortError' ? 'A resposta demorou demais. Tente novamente.' : 'Falha de conexão.' }; })
      .finally(function(){ if (timer) clearTimeout(timer); });
  }

  /* ---- tema claro/escuro ---- */
  var themeBtn = document.getElementById('themeToggle');
  var themeLabel = document.getElementById('themeLabel');
  function currentTheme(){ return document.documentElement.getAttribute('data-theme') || 'light'; }
  function renderTheme(){ if(themeLabel) themeLabel.textContent = currentTheme() === 'dark' ? 'Escuro' : 'Claro'; }
  if(themeBtn){ themeBtn.addEventListener('click', function(){ var t=currentTheme()==='dark'?'light':'dark'; document.documentElement.setAttribute('data-theme',t); try{localStorage.setItem('pm_theme',t);}catch(e){} renderTheme(); }); }
  renderTheme();

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
        if (tStatus) tStatus.textContent = 'Sessão em andamento';
      } else {
        if (btnStart) btnStart.disabled = false;
        if (btnStop) btnStop.disabled = true;
        if (tStatus) tStatus.textContent = 'Cronômetro parado. Inicie quando começar o bloco.';
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

  /* ---- gráficos do dashboard ---- */
  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  var chart = document.getElementById('chartMain');
  if (chart && chart.getContext) {
    var raw = JSON.parse(chart.getAttribute('data-chart') || '{}');
    var labels = Object.keys(raw);
    function draw(days) {
      var ctx = chart.getContext('2d');
      var W = chart.width = Math.max(640, chart.offsetWidth * 2);
      var H = chart.height = 420;
      ctx.clearRect(0, 0, W, H);
      var slice = labels.slice(-days);
      var maxQ = 1, maxH = 1;
      slice.forEach(function (d) {
        maxQ = Math.max(maxQ, Number(raw[d].q || 0));
        maxH = Math.max(maxH, Number(raw[d].s || 0) / 3600);
      });
      var padL = 62, padB = 48, padT = 20;
      var cw = Math.max(12, (W - padL - 20) / Math.max(1, slice.length));
      var muted = cssVar('--muted', '#667085');
      var line = cssVar('--line', '#e5e7eb');
      var accent = cssVar('--accent', '#1769aa');
      var accent2 = cssVar('--accent-2', '#0b3158');
      ctx.font = '20px system-ui, sans-serif';
      for (var g = 0; g <= 4; g++) {
        var y = padT + (H - padT - padB) * g / 4;
        ctx.strokeStyle = line; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(W - 10, y); ctx.stroke();
        ctx.fillStyle = muted; ctx.textAlign = 'right';
        ctx.fillText(String(Math.round(maxQ * (4 - g) / 4)), padL - 8, y + 7);
      }
      var pts = [];
      slice.forEach(function (d, i) {
        var q = Number(raw[d].q || 0), h = Number(raw[d].s || 0) / 3600;
        var bh = (H - padT - padB) * (q / maxQ);
        var x = padL + i * cw + cw * 0.22, w = Math.max(4, cw * 0.56);
        ctx.fillStyle = accent;
        ctx.fillRect(x, H - padB - bh, w, bh);
        pts.push([x + w / 2, H - padB - (H - padT - padB) * (h / maxH)]);
        if (days <= 7 || i % 5 === 0 || i === slice.length - 1) {
          ctx.fillStyle = muted; ctx.textAlign = 'center';
          ctx.fillText(d.slice(8, 10) + '/' + d.slice(5, 7), x + w / 2, H - 14);
        }
      });
      ctx.strokeStyle = accent2; ctx.lineWidth = 4; ctx.beginPath();
      pts.forEach(function (point, i) { i ? ctx.lineTo(point[0], point[1]) : ctx.moveTo(point[0], point[1]); });
      ctx.stroke();
      ctx.fillStyle = accent2;
      pts.forEach(function (point) { ctx.beginPath(); ctx.arc(point[0], point[1], 5, 0, Math.PI * 2); ctx.fill(); });
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

  var subjectChart = document.getElementById('chartSubjects');
  if (subjectChart && subjectChart.getContext) {
    var subjectData = JSON.parse(subjectChart.getAttribute('data-chart') || '[]');
    var sctx = subjectChart.getContext('2d');
    var size = 420;
    subjectChart.width = size;
    subjectChart.height = size;
    var totalSubjects = subjectData.reduce(function (acc, item) { return acc + Number(item.n || 0); }, 0);
    var palette = ['#1769aa','#f97316','#14b8a6','#8b5cf6','#ef4444','#eab308','#64748b','#ec4899','#22c55e'];
    var startAngle = -Math.PI / 2;
    var cx = size / 2, cy = size / 2, radius = 162, inner = 102;
    sctx.clearRect(0,0,size,size);
    if (totalSubjects <= 0) {
      sctx.strokeStyle = cssVar('--line','#e5e7eb'); sctx.lineWidth = radius-inner;
      sctx.beginPath(); sctx.arc(cx,cy,(radius+inner)/2,0,Math.PI*2); sctx.stroke();
    } else {
      subjectData.forEach(function (item, i) {
        var value = Number(item.n || 0);
        if (value <= 0) return;
        var angle = Math.PI * 2 * value / totalSubjects;
        sctx.beginPath(); sctx.arc(cx,cy,radius,startAngle,startAngle+angle); sctx.arc(cx,cy,inner,startAngle+angle,startAngle,true); sctx.closePath();
        sctx.fillStyle = palette[i % palette.length]; sctx.fill();
        startAngle += angle;
      });
    }
    sctx.fillStyle = cssVar('--ink','#111827'); sctx.textAlign='center';
    sctx.font='800 48px system-ui, sans-serif'; sctx.fillText(String(totalSubjects),cx,cy+4);
    sctx.fillStyle = cssVar('--muted','#667085'); sctx.font='600 20px system-ui, sans-serif'; sctx.fillText('resolvidas',cx,cy+36);

    var legend = document.getElementById('subjectLegend');
    if (legend && totalSubjects > 0) {
      legend.innerHTML = subjectData.map(function(item, i){
        var pct = Math.round(Number(item.n||0)*100/totalSubjects);
        return '<div class="donut-legend-row"><span class="donut-dot" style="background:'+palette[i%palette.length]+'"></span><span>'+escapeHtml(item.m||'Geral')+'</span><b>'+pct+'%</b><small>'+Number(item.n||0)+' questões</small></div>';
      }).join('');
    }
  }

  /* ---- radar de domínio ---- */
  var masteryChart=document.getElementById('chartMastery');
  if(masteryChart && masteryChart.getContext){
    var md=JSON.parse(masteryChart.getAttribute('data-chart')||'[]');
    var ctxm=masteryChart.getContext('2d'), size=420;masteryChart.width=size;masteryChart.height=size;
    var cxm=size/2,cym=size/2,rm=145,n=md.length;
    function pt(i,val){var a=-Math.PI/2+i*2*Math.PI/n;return[cxm+Math.cos(a)*rm*val,cym+Math.sin(a)*rm*val];}
    ctxm.clearRect(0,0,size,size);ctxm.font='16px system-ui';ctxm.textAlign='center';ctxm.textBaseline='middle';
    if(n>=3){
      for(var ring=1;ring<=4;ring++){ctxm.beginPath();for(var i=0;i<n;i++){var p0=pt(i,ring/4);i?ctxm.lineTo(p0[0],p0[1]):ctxm.moveTo(p0[0],p0[1]);}ctxm.closePath();ctxm.strokeStyle=cssVar('--line','#ddd');ctxm.lineWidth=1;ctxm.stroke();}
      for(var j=0;j<n;j++){var p1=pt(j,1);ctxm.beginPath();ctxm.moveTo(cxm,cym);ctxm.lineTo(p1[0],p1[1]);ctxm.strokeStyle=cssVar('--line','#ddd');ctxm.stroke();var lp=pt(j,1.18);ctxm.fillStyle=cssVar('--muted','#666');ctxm.fillText(String(md[j].m||'').slice(0,13),lp[0],lp[1]);}
      ctxm.beginPath();md.forEach(function(item,k){var p2=pt(k,Math.max(.05,Number(item.score||0)/100));k?ctxm.lineTo(p2[0],p2[1]):ctxm.moveTo(p2[0],p2[1]);});ctxm.closePath();ctxm.fillStyle='rgba(23,105,170,.18)';ctxm.fill();ctxm.strokeStyle=cssVar('--accent','#1769aa');ctxm.lineWidth=4;ctxm.stroke();
      md.forEach(function(item,k){var p3=pt(k,Math.max(.05,Number(item.score||0)/100));ctxm.beginPath();ctxm.arc(p3[0],p3[1],5,0,Math.PI*2);ctxm.fillStyle=cssVar('--accent','#1769aa');ctxm.fill();});
    }
  }

  /* ---- resolver questão / modo foco ---- */
  var qBox = document.getElementById('questaoBox');
  if (qBox) {
    var qid = qBox.getAttribute('data-qid');
    var t0q = Date.now();
    var answered = qBox.getAttribute('data-answered') === '1';
    var sel = null, confidence = '';
    var qClock = document.getElementById('questionClock');
    function renderQuestionClock(){
      if(!qClock || answered) return;
      var sec=Math.floor((Date.now()-t0q)/1000), m=Math.floor(sec/60), ss=sec%60;
      qClock.textContent=(m<10?'0':'')+m+':'+(ss<10?'0':'')+ss;
    }
    setInterval(renderQuestionClock,1000); renderQuestionClock();

    function selectAlt(value){
      if(answered) return;
      var el=qBox.querySelector('.alt[data-alt="'+value+'"]'); if(!el)return;
      qBox.querySelectorAll('.alt').forEach(function(x){x.classList.remove('selected');});
      el.classList.add('selected'); sel=String(value);
      var btn=document.getElementById('btnResponder'); if(btn)btn.disabled=false;
    }
    qBox.querySelectorAll('.alt').forEach(function (el) {
      el.addEventListener('click', function () { selectAlt(el.getAttribute('data-alt')); });
    });
    qBox.querySelectorAll('[data-confidence]').forEach(function(btn){
      btn.addEventListener('click',function(){
        confidence=btn.getAttribute('data-confidence')||'';
        qBox.querySelectorAll('[data-confidence]').forEach(function(x){x.classList.toggle('selected',x===btn);});
      });
    });

    var btnResp = document.getElementById('btnResponder');
    function submitAnswer(){
      if(!btnResp || sel===null || answered)return;
      btnResp.disabled=true;btnResp.textContent='Corrigindo...';
      api('responder',{qid:qid,alt:sel,tempo:Math.floor((Date.now()-t0q)/1000),confianca:confidence,simulado:qBox.getAttribute('data-simulado')||''}).then(function(res){
        if(!res.ok){alert(res.error||'Erro.');btnResp.disabled=false;btnResp.textContent='Responder';return;}
        answered=true;
        var fb=document.getElementById('feedback');
        if(res.avaliavel===false){
          qBox.querySelectorAll('.alt').forEach(function(x){if(parseInt(x.getAttribute('data-alt'),10)===parseInt(sel,10))x.classList.add('respondida');});
          if(fb){fb.className='feedback info';fb.textContent=res.message||'Resposta registrada; gabarito ainda não validado.';}
        }else{
          qBox.querySelectorAll('.alt').forEach(function(x){var a=parseInt(x.getAttribute('data-alt'),10);if(a===res.gabarito)x.classList.add('certa');if(a===parseInt(sel,10)&&!res.correta)x.classList.add('errada');});
          if(fb){
            fb.className='feedback '+(res.correta?'ok':'no');
            var sourceLabel='';
            if(String(res.fonte||'').indexOf('ia')===0){var pct=Math.round(Number(res.confianca||0)*100);sourceLabel=' · correção assistida por IA'+(pct?' ('+pct+'% de confiança)':'');}
            else if(res.fonte==='oficial')sourceLabel=' · gabarito oficial'; else sourceLabel=' · gabarito validado';
            fb.textContent=(res.correta?'Correta. ':'Incorreta. ')+'Gabarito: letra '+res.letra+sourceLabel;
          }
          var rb=document.getElementById('resolucaoBox');if(rb){rb.style.display='';var body=rb.querySelector('.res-body');if(body)body.innerHTML=res.resolucao_html||escapeHtml(res.resolucao||'');}
          if(!res.correta){var ec=document.getElementById('errorClassifier');if(ec)ec.style.display='';}
        }
        btnResp.style.display='none';
      });
    }
    if(btnResp)btnResp.addEventListener('click',submitAnswer);
    document.addEventListener('keydown',function(ev){
      if(!qBox || /INPUT|TEXTAREA|SELECT/.test((document.activeElement||{}).tagName||''))return;
      var k=String(ev.key||'').toUpperCase(); if(['A','B','C','D','E'].indexOf(k)>=0){selectAlt(['A','B','C','D','E'].indexOf(k));ev.preventDefault();}
      if(ev.key==='Enter'&&sel!==null&&!answered){submitAnswer();ev.preventDefault();}
    });
    qBox.querySelectorAll('[data-error-type]').forEach(function(btn){
      btn.addEventListener('click',function(){
        api('caderno_classificar',{qid:qid,tipo:btn.getAttribute('data-error-type')}).then(function(res){
          if(res.ok){qBox.querySelectorAll('[data-error-type]').forEach(function(x){x.classList.remove('selected');});btn.classList.add('selected');btn.textContent='Classificado';}
        });
      });
    });
    var btnIa = document.getElementById('btnIaQuestao');
    if (btnIa) btnIa.addEventListener('click', function () {
      var box = document.getElementById('iaQuestaoBox'); btnIa.disabled=true;
      if(box){box.style.display='';box.innerHTML='<i>Analisando a questão...</i>';}
      api('ia',{questao_id:qid,pergunta:'Explique esta questão passo a passo e destaque o conceito que eu deveria revisar se errasse.'}).then(function(res){btnIa.disabled=false;if(box)box.innerHTML=res.ok?('<b>Papiro IA</b><br>'+res.resposta):(res.error||'Erro.');});
    });
  }

  /* ---- favoritos ---- */
  document.querySelectorAll('[data-fav]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      api('favorito', { qid: btn.getAttribute('data-fav') }).then(function (res) {
        if (!res.ok) { alert(res.error || 'Erro.'); return; }
        btn.classList.toggle('faved', !!res.fav);
        btn.textContent = res.fav ? 'Favoritada' : 'Favoritar';
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
        var row = chk.closest('.module-card') || chk.closest('.modulo');
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
        btn.textContent = res.ok ? 'Salvo' : 'Erro';
        setTimeout(function () { btn.textContent = 'Salvar anotação'; }, 2000);
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

  /* ---- plano diário ---- */
  document.querySelectorAll('[data-plan-toggle]').forEach(function(chk){
    chk.addEventListener('change',function(){
      var id=chk.getAttribute('data-plan-toggle');
      api('plano_toggle',{id:id}).then(function(res){
        if(!res.ok){chk.checked=!chk.checked;alert(res.error||'Erro.');return;}
        var row=chk.closest('[data-plan-row]');if(row)row.classList.toggle('done',!!res.done);
        var bar=document.getElementById('planBar');if(bar)bar.style.width=res.pct+'%';
      });
    });
  });

  /* ---- revisão espaçada / classificação do caderno ---- */
  document.querySelectorAll('[data-error-select]').forEach(function(selEl){
    selEl.addEventListener('change',function(){api('caderno_classificar',{qid:selEl.getAttribute('data-error-select'),tipo:selEl.value});});
  });
  document.querySelectorAll('[data-review-now]').forEach(function(btn){
    btn.addEventListener('click',function(){
      btn.disabled=true; api('caderno_revisar',{qid:btn.getAttribute('data-review-now')}).then(function(res){
        btn.disabled=false;if(!res.ok){alert(res.error||'Erro.');return;}
        btn.textContent=res.finalizada?'Ciclo concluído':'Revisado · próxima '+res.proxima;
        var row=btn.closest('[data-err-row]');if(row)row.classList.add('reviewed-now');
      });
    });
  });

  /* ---- reportar questão ---- */
  document.querySelectorAll('[data-report-question]').forEach(function(btn){
    btn.addEventListener('click',function(ev){ev.preventDefault();var wrap=btn.closest('.report-form');var type=wrap&&wrap.querySelector('[data-report-type]');var detail=wrap&&wrap.querySelector('[data-report-detail]');btn.disabled=true;api('questao_reportar',{qid:btn.getAttribute('data-report-question'),tipo:type?type.value:'outro',detalhe:detail?detail.value:''}).then(function(res){btn.disabled=false;if(res.ok){btn.textContent='Enviado';if(detail)detail.value='';}else alert(res.error||'Erro.');});});
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
    w.className = 'msg ia'; w.innerHTML = '<i>Analisando...</i>'; log.appendChild(w);
    log.scrollTop = log.scrollHeight;
    inp.value = '';
    api('ia', { pergunta: txt, questao_id: iaForm.getAttribute('data-qid') || '' }).then(function (res) {
      w.innerHTML = res.ok ? res.resposta : (res.error || 'Erro.');
      if (res.ok && res.meta && res.meta.ms) { var sm=document.createElement('div'); sm.className='chat-status'; sm.textContent='Resposta em '+(res.meta.ms/1000).toFixed(1)+'s · '+(res.meta.provider||'IA'); w.appendChild(sm); }
      log.scrollTop = log.scrollHeight;
    });
  });


  /* ---- atalhos de prompt do chat ---- */
  document.querySelectorAll('[data-ia-prompt]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var inp=document.getElementById('iaInput'); if(!inp)return;
      inp.value=btn.getAttribute('data-ia-prompt')||''; inp.focus();
    });
  });

  /* ---- PWA ---- */
  if ('serviceWorker' in navigator && /^https?:$/.test(location.protocol)) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register((PM.base || '') + '/sw.js').catch(function () {});
    });
  }
})();
