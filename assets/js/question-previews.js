/* Prévia visual das questões diretamente do PDF original.
   - lazy render via IntersectionObserver
   - cache do documento PDF por URL (evita reabrir o mesmo arquivo em cada card)
   - recorte vertical y0/y1 preserva gráficos, fórmulas e figuras. */
const libUrl = new URL('../vendor/pdfjs/pdf.min.mjs', import.meta.url).href;
const workerUrl = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

const pdfjsLib = await import(libUrl);
pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
const docs = new Map();
const rendered = new WeakSet();

function docFor(url) {
  if (!docs.has(url)) docs.set(url, pdfjsLib.getDocument({ url, withCredentials: false }).promise);
  return docs.get(url);
}

async function renderBox(box) {
  if (rendered.has(box)) return;
  rendered.add(box);
  const url = box.dataset.pdf;
  const pageNum = Math.max(1, parseInt(box.dataset.page || '1', 10));
  const y0 = Math.max(0, Math.min(1, parseFloat(box.dataset.y0 || '0')));
  const y1 = Math.max(0, Math.min(1, parseFloat(box.dataset.y1 || '1')));
  const canvas = box.querySelector('canvas');
  const placeholder = box.querySelector('.question-preview-placeholder');
  if (!url || !canvas || !(y1 > y0)) return;
  try {
    const doc = await docFor(url);
    const page = await doc.getPage(Math.min(pageNum, doc.numPages));
    const dpr = Math.min(window.devicePixelRatio || 1, 1.6);
    const base = page.getViewport({ scale: 1.18 * dpr });
    const full = document.createElement('canvas');
    full.width = Math.max(1, Math.floor(base.width));
    full.height = Math.max(1, Math.floor(base.height));
    await page.render({ canvasContext: full.getContext('2d'), viewport: base }).promise;
    const sy = Math.max(0, Math.floor(full.height * y0));
    const ey = Math.min(full.height, Math.ceil(full.height * y1));
    const sh = Math.max(1, ey - sy);
    canvas.width = full.width;
    canvas.height = sh;
    canvas.getContext('2d').drawImage(full, 0, sy, full.width, sh, 0, 0, full.width, sh);
    canvas.hidden = false;
    if (placeholder) placeholder.remove();
  } catch (e) {
    if (placeholder) placeholder.textContent = 'Prévia indisponível. Abra a questão para consultar o PDF.';
  }
}

const boxes = Array.from(document.querySelectorAll('[data-question-preview]'));
// As três primeiras prévias são desenhadas imediatamente para a página já abrir
// com os "prints" visíveis. As demais continuam em lazy-load para preservar velocidade.
const immediate = boxes.slice(0, 3);
const lazyBoxes = boxes.slice(3);
immediate.forEach(box => renderBox(box));

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                renderBox(entry.target);
            });
        },
        { rootMargin: '520px 0px' }
    );

    lazyBoxes.forEach(box => observer.observe(box));
} else {
    lazyBoxes.forEach(box => renderBox(box));
}
