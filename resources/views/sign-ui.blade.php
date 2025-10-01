<!-- <!DOCTYPE html>
<html>
<head>
    <title>UgPass Signing UI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <h2 class="mb-4">UgPass Signing Portal</h2>

        <ul class="nav nav-tabs" id="signTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#single" type="button">Single Sign</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bulk" type="button">Bulk Sign</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qr" type="button">Embed QR</button>
            </li>
        </ul>

        <div class="tab-content mt-3">
            {{-- Single Sign --}}
            <div class="tab-pane fade show active" id="single">
                <form method="POST" action="{{ route('sign.single') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Upload PDF</label>
                        <input type="file" name="document" class="form-control" required>
                    </div>
                    <button class="btn btn-success">Sign Document</button>
                </form>
            </div>

            {{-- Bulk Sign --}}
            <div class="tab-pane fade" id="bulk">
                <form method="POST" action="{{ route('sign.bulk') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Upload Multiple PDFs</label>
                        <input type="file" name="documents[]" class="form-control" multiple required>
                    </div>
                    <button class="btn btn-primary">Bulk Sign</button>
                </form>
            </div>

            {{-- QR Embed --}}
            <div class="tab-pane fade" id="qr">
                 <form action="{{ route('pdf.embedQr') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label for="file">Select PDF File</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Generate QR Embedded PDF</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Auto-download if session has file --}}
@if(session('download'))
    <a id="autoDownload" href="{{ route('download.signed', ['file' => session('download')]) }}" style="display:none;"></a>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("autoDownload").click();
        });
    </script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
 --><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PDF Signer Fixed</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body { padding: 20px; }
#pdfContainer { position: relative; display: inline-block; border: 1px solid #ccc; margin-top: 10px; }
#pdfViewer { display: block; width: auto; height: auto; }
#signature {
    width: 120px;
    height: 60px;
    background: rgba(0,128,255,0.1);
    border: 2px dashed #007bff;
    cursor: move;
    position: absolute;
    display: none;
    padding: 5px;
    font-size: 14px;
    text-align: center;
    line-height: 50px;
    z-index: 10;
    box-sizing: border-box;
}
#signature #resizeHandle {
    width: 12px;
    height: 12px;
    background: #007bff;
    position: absolute;
    right: 0;
    bottom: 0;
    cursor: se-resize;
}
pre { background: #f8f9fa; padding: 10px; border-radius: 6px; }
</style>
</head>
<body>
<div class="container">
<h3 class="mb-3">PDF Signing Prototype</h3>
<form id="signForm" method="POST" action="{{ route('sign.single') }}" enctype="multipart/form-data">
    @csrf
    <input type="file" id="pdfUpload" name="document" accept="application/pdf" class="form-control mb-3" required>

    <input type="hidden" name="pageNumber" id="inputPageNumber">
    <input type="hidden" name="signatureXaxis" id="inputX">
    <input type="hidden" name="signatureYaxis" id="inputY">
    <input type="hidden" name="signatureWidth" id="inputWidth">
    <input type="hidden" name="signatureHeight" id="inputHeight">

    <div class="mb-2">
        <button type="button" class="btn btn-secondary btn-sm" id="prevPage">Previous</button>
        <button type="button" class="btn btn-secondary btn-sm" id="nextPage">Next</button>
        <button type="button" class="btn btn-secondary btn-sm" id="lastPage">Go to Last</button>
        <span class="ms-3">Page: <span id="pageNum">1</span> / <span id="pageCount">1</span></span>
    </div>

    <div id="pdfContainer">
        <canvas id="pdfViewer"></canvas>
        <div id="signature">
            ✍️ Sign Here
            <div id="resizeHandle"></div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">Sign Document</button>
</form>

<div class="mt-4">
    <h5>Captured Coordinates</h5>
    <pre id="coordsOutput"></pre>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";

let pdfDoc = null, pageNum = 1,
    canvas = document.getElementById('pdfViewer'),
    ctx = canvas.getContext('2d');

let signature = document.getElementById("signature");
let coordsOutput = document.getElementById("coordsOutput");
let pageNumSpan = document.getElementById("pageNum");
let pageCountSpan = document.getElementById("pageCount");

let signaturePositions = {};
let currentViewportScale = 1;

// Load PDF
document.getElementById('pdfUpload').addEventListener('change', function(e){
    let file = e.target.files[0];
    if(file){
        let reader = new FileReader();
        reader.onload = function() {
            let typedarray = new Uint8Array(this.result);
            pdfjsLib.getDocument(typedarray).promise.then(function(pdf){
                pdfDoc = pdf;
                pageCountSpan.textContent = pdf.numPages;
                renderPage(pageNum);
            });
        };
        reader.readAsArrayBuffer(file);
    }
});

// Render page at natural resolution
function renderPage(num){
    pdfDoc.getPage(num).then(function(page){
        const scale = 1; // natural size for exact coordinates
        currentViewportScale = scale;
        const viewport = page.getViewport({ scale: scale });
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.style.width = viewport.width + "px";
        canvas.style.height = viewport.height + "px";

        page.render({ canvasContext: ctx, viewport: viewport });
        pageNumSpan.textContent = num;

        if(signaturePositions[num]){
            let pos = signaturePositions[num];
            signature.style.left = pos.left + "px";
            signature.style.top = pos.top + "px";
            signature.style.width = pos.width + "px";
            signature.style.height = pos.height + "px";
        } else {
            signature.style.left = "50px";
            signature.style.top = "50px";
            signature.style.width = "120px";
            signature.style.height = "60px";
        }
        signature.style.display = "block";
        captureCoords();
    });
}

// Navigation
document.getElementById("prevPage").addEventListener("click", ()=>{ if(pageNum>1){ pageNum--; renderPage(pageNum); } });
document.getElementById("nextPage").addEventListener("click", ()=>{ if(pageNum<pdfDoc.numPages){ pageNum++; renderPage(pageNum); } });
document.getElementById("lastPage").addEventListener("click", ()=>{ pageNum = pdfDoc.numPages; renderPage(pageNum); });

// Get mouse position relative to canvas container
function getMousePosOnCanvas(e){
    const rect = canvas.getBoundingClientRect();
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

// Dragging
let isDragging=false, offsetX=0, offsetY=0;
signature.addEventListener('mousedown', function(e){
    if(e.target.id === 'resizeHandle') return;
    isDragging = true;
    const mousePos = getMousePosOnCanvas(e);
    offsetX = mousePos.x - parseFloat(signature.style.left);
    offsetY = mousePos.y - parseFloat(signature.style.top);
    e.preventDefault();
});
document.addEventListener('mousemove', function(e){
    if(isDragging){
        const mousePos = getMousePosOnCanvas(e);
        let x = mousePos.x - offsetX;
        let y = mousePos.y - offsetY;

        x = Math.max(0, Math.min(x, canvas.width - signature.offsetWidth));
        y = Math.max(0, Math.min(y, canvas.height - signature.offsetHeight));

        signature.style.left = x + "px";
        signature.style.top = y + "px";
    }
    if(isResizing) resizeSignature(e);
});
document.addEventListener('mouseup', function(){
    if(isDragging){ isDragging=false; captureCoords(); }
    if(isResizing){ isResizing=false; captureCoords(); }
});

// Resizing
let isResizing=false, startX, startY, startWidth, startHeight;
const resizeHandle = document.getElementById('resizeHandle');
resizeHandle.addEventListener('mousedown', function(e){
    isResizing = true;
    startX = e.clientX;
    startY = e.clientY;
    startWidth = signature.offsetWidth;
    startHeight = signature.offsetHeight;
    e.stopPropagation();
    e.preventDefault();
});
function resizeSignature(e){
    let newWidth = startWidth + (e.clientX - startX);
    let newHeight = startHeight + (e.clientY - startY);

    newWidth = Math.max(40, Math.min(newWidth, canvas.width - parseFloat(signature.style.left)));
    newHeight = Math.max(20, Math.min(newHeight, canvas.height - parseFloat(signature.style.top)));

    signature.style.width = newWidth + 'px';
    signature.style.height = newHeight + 'px';
}

// Capture coordinates relative to PDF
function captureCoords(){
    const left = parseFloat(signature.style.left);
    const top = parseFloat(signature.style.top);
    const width = signature.offsetWidth;
    const height = signature.offsetHeight;

    const pdfX = left + width/2;
    const pdfY = canvas.height - (top + height/2);

    signaturePositions[pageNum] = { left, top, width, height };

    document.getElementById("inputPageNumber").value = pageNum;
    document.getElementById("inputX").value = Math.round(pdfX);
    document.getElementById("inputY").value = Math.round(pdfY);
    document.getElementById("inputWidth").value = Math.round(width);
    document.getElementById("inputHeight").value = Math.round(height);

    coordsOutput.textContent = JSON.stringify({
        pageNumber: pageNum,
        signatureXaxis: Math.round(pdfX),
        signatureYaxis: Math.round(pdfY),
        imageWidth: Math.round(width),
        imageHeight: Math.round(height)
    }, null, 2);
}

document.getElementById('signForm').addEventListener('submit', function(){ captureCoords(); });
</script>




@if(session('download'))
    <a id="autoDownload" href="{{ route('download.signed', ['file' => session('download')]) }}" style="display:none;"></a>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("autoDownload").click();
        });
    </script>
@endif
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





