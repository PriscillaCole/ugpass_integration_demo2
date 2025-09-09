<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UgpassSigningService;
use ZipArchive;

class UgpassSigningController extends Controller
{
    public function __construct(private UgpassSigningService $service) {}

    /** Single Document Sign */
     public function signSingle(Request $request)
    {
         
        $request->validate([
            'document' => 'required|mimes:pdf|max:5120',
        ]);

        
        $path = $request->file('document')->store('uploads', 'public');


        $result = $this->service->signDocument(
            storage_path("app/public/{$path}"),
            session('ugpass_user')['daes_claims']['email']
        );

        // Debug: inspect the raw result from UgPass service
        dd($result);

        $downloadFile = isset($result['savedPath']) ? basename($result['savedPath']) : null;

        return redirect()->route('sign.ui')->with('download', $downloadFile);
    }


    /** Bulk Document Signing (returns ZIP) */
    public function bulkSign(Request $request)
    {
        $request->validate([
            'documents.*' => 'required|mimes:pdf|max:5120',
        ]);

        $paths = [];
        foreach ($request->file('documents') as $file) {
            $paths[] = $file->store('uploads', 'public');
        }

        $files = array_map(fn($p) => storage_path("app/public/{$p}"), $paths);

        $result = $this->service->bulkSign($files, "placeholder@example.com");

        $zipFile = null;

        if (isset($result['documents'])) {
            $zip = new ZipArchive();
            $zipDir = storage_path('app/signed');
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0777, true);
            }

            $zipFile = $zipDir . '/bulk-signed-' . time() . '.zip';

            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                foreach ($result['documents'] as $doc) {
                    if (isset($doc['savedPath']) && file_exists($doc['savedPath'])) {
                        $zip->addFile($doc['savedPath'], basename($doc['savedPath']));
                    }
                }
                $zip->close();
            }
        }

        return redirect()->route('sign.ui')->with('download', basename($zipFile));
    }

    /** Embed Crypto QR */
    public function embedQr(Request $request)
    {
        $request->validate([
            'document' => 'required|mimes:pdf|max:5120',
        ]);

        $path = $request->file('document')->store('uploads', 'public');

        $metaData = ["invoiceNo" => $request->invoiceNo ?? "12345", "date" => now()->toDateString()];
        $secretData = ["secretCode" => $request->secretCode ?? "ABC123"];

        $result = $this->service->embedCryptoQR(
            storage_path("app/public/{$path}"),
            $metaData,
            $secretData
        );

        $downloadFile = isset($result['savedPath']) ? basename($result['savedPath']) : null;

        return redirect()->route('sign.ui')->with('download', $downloadFile);
    }
}
