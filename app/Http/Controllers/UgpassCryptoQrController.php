<?php

// app/Http/Controllers/UgPassCryptoQrController.php
namespace App\Http\Controllers;

use App\Services\UgPassCryptoQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class UgPassCryptoQrController extends Controller
{
    public function __construct(private UgPassCryptoQrService $qr) {}

    public function embedBulk(Request $request)
    {
     // In UgPassCryptoQrController@embedBulk (from earlier)
$request->validate([
  'documents'   => 'required',
  'documents.*' => 'file|mimes:pdf|max:5120',
  'placements'  => 'required|string',
  'publicData'  => 'required|string',
  'privateData' => 'required|string',
]);

$allPlacements = json_decode($request->input('placements'), true) ?? [];
// For simplicity, pick the first placement
$first = $allPlacements[0] ?? ['pageNumber'=>1,'signatureXaxis'=>300,'signatureYaxis'=>400,'imageWidth'=>120,'imageHeight'=>120];

$coords = [
  'pageNumber' => (int)$first['pageNumber'],
  'x'          => (float)$first['signatureXaxis'],
  'y'          => (float)$first['signatureYaxis'],
  'width'      => (float)$first['imageWidth'],
  'height'     => (float)$first['imageHeight'],
];

        $qrData = [
            'publicData'  => $request->input('publicData'),
            'privateData' => $request->input('privateData'),
            // 'photo'    => $request->input('photo'),
        ];

        $outputs = [];
        $errors  = [];

        foreach ($request->file('documents') as $file) {
            $tmp = $file->store('ugpass/qr/tmp', 'local');
            $abs = storage_path('app/'.$tmp);

            $res = $this->qr->embedQr($abs, $coords, $qrData);
            if (!($res['success'] ?? false)) {
                $errors[] = [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $res['body'] ?? $res['message'] ?? 'Unknown error',
                ];
                continue;
            }

            $payload = $res['data'] ?? [];
            $b64     = $payload['result'] ?? null;

            if (!$b64) {
                $errors[] = ['file' => $file->getClientOriginalName(), 'error' => 'No result content'];
                continue;
            }

            $pdfBytes = base64_decode($b64, true);
            if ($pdfBytes === false) {
                $errors[] = ['file' => $file->getClientOriginalName(), 'error' => 'Base64 decode failed'];
                continue;
            }

            $nameNoExt = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $rel       = "ugpass/qr/{$nameNoExt}-qr.pdf";
            Storage::disk('public')->put($rel, $pdfBytes);

            $outputs[] = [
                'name' => "{$nameNoExt}-qr.pdf",
                'path' => $rel,
                'url'  => Storage::disk('public')->url($rel),
            ];
        }

        // Optional ZIP
        $zipUrl = null;
        if (count($outputs) > 1) {
            $zipRel = 'ugpass/qr/batch-qr-'.time().'.zip';
            $zipAbs = storage_path('app/public/'.$zipRel);
            @mkdir(dirname($zipAbs), 0777, true);

            $zip = new ZipArchive();
            if ($zip->open($zipAbs, ZipArchive::CREATE) === true) {
                foreach ($outputs as $o) {
                    $zip->addFile(storage_path('app/public/'.$o['path']), $o['name']);
                }
                $zip->close();
                $zipUrl = Storage::disk('public')->url($zipRel);
            }
        }

        return response()->json([
            'success'   => empty($errors),
            'downloads' => $outputs,
            'zip'       => $zipUrl,
            'errors'    => $errors,
        ]);
    }
}

