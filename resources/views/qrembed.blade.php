<!-- Include PDF.js (official builds) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.2.67/pdf.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/modern-normalize/2.0.0/modern-normalize.min.css"/>

<style>
  .pdf-page { position: relative; margin-bottom: 24px; }
  .qr-box {
    position: absolute; border: 2px solid #0f62fe; cursor: move;
    box-shadow: 0 0 0 2px rgba(15,98,254,.2) inset;
  }
  .qr-resize {
    position: absolute; width: 12px; height: 12px; background: #0f62fe;
    right: -6px; bottom: -6px; cursor: se-resize; border-radius: 2px;
  }
  .controls { display:flex; gap:12px; flex-wrap:wrap; margin: 16px 0; }
  .controls input { width: 120px; }
</style>

<div class="controls">
  <input type="file" id="pdfInput" accept="application/pdf" required>
  <label>QR width (px): <input type="number" id="qrW" value="120" min="24"></label>
  <label>QR height (px): <input type="number" id="qrH" value="120" min="24"></label>
  <button id="clearBoxes" type="button">Clear placements</button>
</div>

<div id="pdfContainer"></div>

<!-- Hidden form that will be posted to your QR endpoint -->
<form id="qrForm" method="post" action="{{ route('ugpass.qr.embed') }}" enctype="multipart/form-data">
  @csrf
  <input type="file" id="docsInput" name="documents[]" accept="application/pdf" multiple hidden>
  <input type="hidden" name="placements" id="placements">
  <input type="hidden" name="publicData" value='{"doc":"Example"}'>
  <input type="hidden" name="privateData" value='{"secret":"UGX 2,500,000"}'>
  <button type="submit">Embed QR and Upload</button>
</form>

<script>
const pdfjsLib = window['pdfjsLib'];

// Render the selected PDF for placement
const pdfInput = document.getElementById('pdfInput');
const pdfContainer = document.getElementById('pdfContainer');
const clearBoxesBtn = document.getElementById('clearBoxes');
const qrW = document.getElementById('qrW');
const qrH = document.getElementById('qrH');
const placementsField = document.getElementById('placements');
const docsInput = document.getElementById('docsInput'); // the real upload input
const qrForm = document.getElementById('qrForm');

// Store placements per page: { pageNumber, canvasSize: {w,h}, pdfSize: {w,h}, boxes: [{x,y,w,h}] }
let placementState = [];

pdfInput.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  // Mirror the chosen PDF into the real upload control
  docsInput.files = pdfInput.files;

  pdfContainer.innerHTML = '';
  placementState = [];
  const arrayBuffer = await file.arrayBuffer();

  // Load the PDF
  const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

  for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
    const page = await pdf.getPage(pageNum);

    // Get the PDF's native size (points). PDF.js uses 72 DPI points internally.
    const viewport = page.getViewport({ scale: 1.0 });
    const pdfWidthPt = viewport.width;   // in points
    const pdfHeightPt = viewport.height; // in points

    // Choose a canvas scale (fits to width ~900px)
    const scale = Math.min(1.5, 900 / viewport.width);
    const view = page.getViewport({ scale });

    // Build DOM
    const wrap = document.createElement('div');
    wrap.className = 'pdf-page';
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = view.width;
    canvas.height = view.height;
    wrap.appendChild(canvas);
    pdfContainer.appendChild(wrap);

    // Render
    await page.render({ canvasContext: ctx, viewport: view }).promise;

    // Initialize state for this page
    placementState.push({
      pageNumber: pageNum,
      canvasSize: { w: canvas.width, h: canvas.height },
      pdfSize: { w: pdfWidthPt, h: pdfHeightPt },
      scale,
      boxes: []
    });

    // Allow adding a QR box by clicking on the page
    wrap.addEventListener('click', (ev) => {
      // Avoid adding when clicking on existing box
      if (ev.target.closest('.qr-box')) return;

      const rect = canvas.getBoundingClientRect();
      const clickX = ev.clientX - rect.left;
      const clickY = ev.clientY - rect.top;

      addQrBox(wrap, pageNum, clickX, clickY, parseInt(qrW.value,10), parseInt(qrH.value,10));
    });
  }
});

// Create a draggable + resizable box
function addQrBox(container, pageNumber, x, y, w, h) {
  const pageState = placementState.find(p => p.pageNumber === pageNumber);
  const box = document.createElement('div');
  box.className = 'qr-box';
  box.style.left = Math.max(0, Math.min(x, pageState.canvasSize.w - w)) + 'px';
  box.style.top  = Math.max(0, Math.min(y, pageState.canvasSize.h - h)) + 'px';
  box.style.width  = w + 'px';
  box.style.height = h + 'px';

  const grip = document.createElement('div');
  grip.className = 'qr-resize';
  box.appendChild(grip);
  container.appendChild(box);

  // Persist in state
  pageState.boxes.push({
    x: parseFloat(box.style.left),
    y: parseFloat(box.style.top),
    w: w,
    h: h
  });

  // Dragging
  let drag = false, startX, startY, startL, startT, current;
  box.addEventListener('mousedown', (e) => {
    if (e.target === grip) return; // resizing case
    drag = true; current = pageState.boxes.find(b => b === getBoxState(pageState, box));
    startX = e.clientX; startY = e.clientY;
    startL = parseFloat(box.style.left); startT = parseFloat(box.style.top);
    e.preventDefault();
  });
  window.addEventListener('mousemove', (e) => {
    if (!drag) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    const newL = clamp(startL + dx, 0, pageState.canvasSize.w - parseFloat(box.style.width));
    const newT = clamp(startT + dy, 0, pageState.canvasSize.h - parseFloat(box.style.height));
    box.style.left = newL + 'px';
    box.style.top  = newT + 'px';
    current.x = newL; current.y = newT;
  });
  window.addEventListener('mouseup', () => drag = false);

  // Resizing
  let resizing = false, rStartX, rStartY, rStartW, rStartH, rCurrent;
  grip.addEventListener('mousedown', (e) => {
    resizing = true; rCurrent = pageState.boxes.find(b => b === getBoxState(pageState, box));
    rStartX = e.clientX; rStartY = e.clientY;
    rStartW = parseFloat(box.style.width); rStartH = parseFloat(box.style.height);
    e.preventDefault();
    e.stopPropagation();
  });
  window.addEventListener('mousemove', (e) => {
    if (!resizing) return;
    const dw = e.clientX - rStartX;
    const dh = e.clientY - rStartY;
    const newW = clamp(rStartW + dw, 24, pageState.canvasSize.w - parseFloat(box.style.left));
    const newH = clamp(rStartH + dh, 24, pageState.canvasSize.h - parseFloat(box.style.top));
    box.style.width = newW + 'px';
    box.style.height = newH + 'px';
    rCurrent.w = newW; rCurrent.h = newH;
  });
  window.addEventListener('mouseup', () => resizing = false);
}

function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }
function getBoxState(pageState, el){
  const L = parseFloat(el.style.left), T = parseFloat(el.style.top);
  const W = parseFloat(el.style.width), H = parseFloat(el.style.height);
  return pageState.boxes.find(b => b.x===L && b.y===T && b.w===W && b.h===H);
}

// Clear placements
clearBoxesBtn.addEventListener('click', () => {
  document.querySelectorAll('.qr-box').forEach(n => n.remove());
  placementState.forEach(p => p.boxes = []);
});

// Before submit: convert pixel boxes → PDF coordinates
qrForm.addEventListener('submit', (e) => {
  // Build placements for first (or all) boxes; for CryptoQR you usually place one per doc.
  // If multiple boxes per page are allowed, keep them all.
  const placements = placementState.flatMap(p => {
    // Canvas origin is top-left; many APIs expect top-left too.
    // If your API expects bottom-left origin, convert Y like: pdfY = pdfHeightPt - topPt - heightPt
    const scaleX = p.pdfSize.w / p.canvasSize.w;
    const scaleY = p.pdfSize.h / p.canvasSize.h;

    return p.boxes.map(b => {
      const xPt = b.x * scaleX;
      const yTopPt = b.y * scaleY;
      const wPt = b.w * scaleX;
      const hPt = b.h * scaleY;

      // Adjust this if UgPass expects bottom-left:
      const originBottomLeft = false; // set true if needed
      const yPt = originBottomLeft ? (p.pdfSize.h - yTopPt - hPt) : yTopPt;

      return {
        pageNumber: p.pageNumber,
        signatureXaxis: xPt,
        signatureYaxis: yPt,
        imageWidth: wPt,
        imageHeight: hPt
      };
    });
  });

  placementsField.value = JSON.stringify(placements);
});
</script>
