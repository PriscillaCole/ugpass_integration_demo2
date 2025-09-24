<?php
namespace App\Http\Controllers;
use App\Services\UgPassCryptoQrService;
use Illuminate\Http\Request;

class UgPassCryptoQrController extends Controller
{
   public function embed(Request $request, UgPassCryptoQrService $qrService)
    {
        $request->validate([
            'file' => 'required|mimes:pdf|max:20480', 
        ]);

        $path = $request->file('file')->store('uploads', 'public');

        $response = $qrService->embedQr(storage_path("app/public/{$path}"));

        if (!$response['success']) {
            return back()->with('error', $response['message'] ?? 'QR embedding failed.');
        }

        // decode PDF from base64
        $pdfBase64 = $response['data']['result'] ?? null;
        if (!$pdfBase64) {
            return back()->with('error', 'Invalid UgPass response.');
        }

        $pdfData = base64_decode($pdfBase64);
        $filename = 'qr_embedded_' . time() . '.pdf';
        $outputPath = storage_path("app/{$filename}");
        file_put_contents($outputPath, $pdfData);

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

}

