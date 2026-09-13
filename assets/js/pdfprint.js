/* Papiro Máximo — prints automáticos com PDF.js local (sem renderizador no servidor).
   .pdfprint   = página inteira dentro de <details> (renderiza ao abrir, economiza).
   .pdfcropbox = recorte da região da questão, exibido como figura (renderiza na hora).
   Falhou? Print mostra link p/ o PDF; recorte some silenciosamente (o print cobre). */
const libUrl = new URL('../vendor/pdfjs/pdf.min.mjs', import.meta.url).href;
const workerUrl = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

function withTimeout(promise, ms) {
  let timer = null;
  const t = new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('timeout')), ms); });
  return Promise.race([promise, t]).finally(() => clearTimeout(timer));
}

function pdfLink(box) {
  const a = document.createElement('a');
  a.href = (box.getAttribute('data-pdf') || '#') + '#page=' + (box.getAttribute('data-page') || '1');
  a.target = '_blank';
  a.rel = 'noopener';
  return a;
}

/* Renderiza a página num canvas. y0/y1 (fração 0..1) recortam a faixa vertical; null = página cheia. */
async function renderPageCanvas(pdfjsLib, pdfUrl, pageNum, scale, y0, y1) {
  const doc = await withTimeout(pdfjsLib.getDocument({ url: pdfUrl, withCredentials: false }).promise, 25000);
  try {
    const n = Math.min(Math.max(1, pageNum), doc.numPages);
    const page = await doc.getPage(n);
    const viewport = page.getViewport({ scale: scale });
    const full = document.createElement('canvas');
    full.width = Math.floor(viewport.width);
    full.height = Math.floor(viewport.height);
    await withTimeout(page.render({ canvasContext: full.getContext('2d'), viewport }).promise, 25000);
    if (y0 === null || y1 === null || !(y1 > y0)) return { canvas: full, page: n, total: doc.numPages };
    const sy = Math.floor(full.height * Math.max(0, y0));
    const sh = Math.max(1, Math.floor(full.height * Math.min(1, y1)) - sy);
    const crop = document.createElement('canvas');
    crop.width = full.width;
    crop.height = sh;
    crop.getContext('2d').drawImage(full, 0, sy, full.width, sh, 0, 0, full.width, sh);
    return { canvas: crop, page: n, total: doc.numPages };
  } finally {
    await doc.destroy().catch(() => {});
  }
}

try {
  const pdfjsLib = await import(libUrl);
  pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
  const dpr = Math.min(window.devicePixelRatio || 1, 2);

  /* 1) recortes (figura da questão) — renderiza imediatamente */
  for (const fig of document.querySelectorAll('.pdfcropbox')) {
    const canvas = fig.querySelector('canvas');
    const pdfUrl = fig.getAttribute('data-pdf');
    const y0 = parseFloat(fig.getAttribute('data-y0') || '0');
    const y1 = parseFloat(fig.getAttribute('data-y1') || '0');
    if (!pdfUrl || !canvas || !(y1 > y0)) { fig.style.display = 'none'; continue; }
    const pageNum = parseInt(fig.getAttribute('data-page') || '1', 10) || 1;
    try {
      const r = await renderPageCanvas(pdfjsLib, pdfUrl, pageNum, 2 * dpr, y0, y1);
      canvas.width = r.canvas.width;
      canvas.height = r.canvas.height;
      canvas.getContext('2d').drawImage(r.canvas, 0, 0);
    } catch (e) { fig.style.display = 'none'; }
  }

  /* 2) prints de página inteira — renderiza ao abrir o <details> */
  for (const box of document.querySelectorAll('.pdfprint')) {
    const det = box.closest('details');
    const go = async () => {
      if (box.getAttribute('data-done') === '1') return;
      box.setAttribute('data-done', '1');
      const status = box.querySelector('.pdfprint-status');
      const canvas = box.querySelector('canvas');
      const pdfUrl = box.getAttribute('data-pdf');
      if (!pdfUrl || !canvas) return;
      const pageNum = parseInt(box.getAttribute('data-page') || '1', 10) || 1;
      try {
        const r = await renderPageCanvas(pdfjsLib, pdfUrl, pageNum, 1.6 * dpr, null, null);
        canvas.width = r.canvas.width;
        canvas.height = r.canvas.height;
        canvas.getContext('2d').drawImage(r.canvas, 0, 0);
        canvas.style.display = '';
        if (status) status.style.display = 'none';
        if (r.page !== pageNum && status) {
          status.style.display = '';
          status.textContent = 'ℹ️ O PDF tem ' + r.total + ' páginas; mostrando a última.';
        }
      } catch (e) {
        if (!status) return;
        status.textContent = '⚠️ não consegui gerar o print automático. ';
        const a = pdfLink(box);
        a.textContent = 'Abrir o PDF ↗';
        status.appendChild(a);
      }
    };
    if (det && !det.open) {
      const status = box.querySelector('.pdfprint-status');
      if (status) status.textContent = '🖨️ O print gera ao abrir...';
      det.addEventListener('toggle', function h() {
        if (det.open) { det.removeEventListener('toggle', h); go(); }
      });
    } else {
      go();
    }
  }
} catch (e) {
  for (const box of document.querySelectorAll('.pdfprint')) {
    const status = box.querySelector('.pdfprint-status');
    if (!status) continue;
    status.textContent = '⚠️ não consegui carregar o gerador de prints. ';
    const a = pdfLink(box);
    a.textContent = 'Abrir o PDF ↗';
    status.appendChild(a);
  }
  for (const fig of document.querySelectorAll('.pdfcropbox')) fig.style.display = 'none';
}
