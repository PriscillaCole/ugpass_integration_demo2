<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UgpassSigningService;
use ZipArchive;
// log
use Illuminate\Support\Facades\Log;

class UgpassSigningController extends Controller
{
    public function __construct(private UgpassSigningService $service) {}
   // Single Document Sign
    public function signSingle(Request $request)
    {
        $request->validate([
            'document' => 'required|mimes:pdf|max:5120',
        ]);

        try {
            // Store uploaded file
            $path = $request->file('document')->store('uploads', 'public');

            $result = $this->service->signDocument(
                storage_path("app/public/{$path}"),
                session('ugpass_user')['daes_claims']['email'] ?? 'unknown@example.com', true
            );

            $downloadFile = isset($result['savedPath']) ? basename($result['savedPath']) : null;

            return redirect()->route('sign.ui')->with([
                'download' => $downloadFile,
                'result'   => $result,
            ]);
        } catch (\Throwable $e) {
        
            Log::error('SignSingle failed', [
                'exception' => $e,
                'file'      => $request->file('document')->getClientOriginalName(),
            ]);

        
            return redirect()->route('sign.ui')->withErrors([
                'signing' => 'Signing failed. Please try again or contact support.',
                'details' => $e->getMessage(),
            ]);
        }
    }


    
}
