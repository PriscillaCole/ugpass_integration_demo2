<!DOCTYPE html>
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

