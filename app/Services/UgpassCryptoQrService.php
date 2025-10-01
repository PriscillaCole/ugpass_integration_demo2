<?php

// app/Services/UgPassCryptoQrService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgPassCryptoQrService
{
   public function embedQr(string $filePath): array
{
    $url = config('services.ugpass.qr');
    $tokens = session('ugpass');

    if (!$tokens || empty($tokens['access_token'])) {
        return [
            'success' => false,
            'message' => 'No UgPass access token found. Please login again.'
        ];
    }

    // Check that the file exists before sending
    if (!file_exists($filePath)) {
        return [
            'success' => false,
            'message' => "File not found: {$filePath}"
        ];
    }

    $model = [
        'qrPlaceHolderCoordinates' => [
            'pageNumber'    => 1,
            'signatureXaxis'=> 200,
            'signatureYaxis'=> 300,
            'imageWidth'   => 100,
            'imageHeight'  => 100,
        ],
        'qrData' => [
            'publicData'  => json_encode(['docId' => '12345']),
            'privateData' => json_encode(['secret' => 'xyz']),
            'photo'       => null,
        ],
    ];

    try {
        $resp = Http::withToken($tokens['access_token']) // use standard Authorization header
            ->acceptJson()
            ->asMultipart()
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->attach('model', json_encode($model)) // send JSON as string (no filename)
            ->post($url);
dd($url);
        // Debug info if the response is empty or failed
        if (!$resp->successful()) {
            Log::error('UgPass CryptoQR embed failed', [
                'status' => $resp->status(),
                'headers' => $resp->headers(),
                'body' => $resp->body(),
            ]);

            return [
                'success' => false,
                'status' => $resp->status(),
                'headers' => $resp->headers(),
                'body' => $resp->body(),
            ];
        }

        // Try decoding JSON response
        $json = $resp->json();
        return [
            'success' => true,
            'data' => $json,
        ];

    } catch (\Exception $e) {
        // Catch network or parsing errors
        Log::error('UgPass CryptoQR embed exception', [
            'message' => $e->getMessage(),
            'file' => $filePath,
        ]);

        return [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }
}

}
