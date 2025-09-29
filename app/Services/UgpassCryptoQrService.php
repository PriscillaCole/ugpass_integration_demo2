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
            return ['success' => false, 'message' => 'No UgPass access token found. Please login again.'];
        }
      

        $model = [
            'qrPlaceHolderCoordinates' => [
                    'pageNumber'   => 1,
                    'signatureXaxis' => 200,
                    'signatureYaxis' => 300,
                    'imageWidth'    => 100,
                    'imageHeight'   => 100,
            ],
            'qrData' =>[
                'publicData'  => json_encode(['docId' => '12345']),
                'privateData' => json_encode(['secret' => 'xyz']),
                'photo'       => null
            ],
            ];

            dd($model);

       $resp = Http::withHeaders([
        'Accept' => 'application/json',
        'UgPassAuthorization' => 'Bearer '.$tokens['access_token'],
    ])
    ->asMultipart()
    ->attach('file', file_get_contents($filePath), basename($filePath))
    ->attach('model', json_encode($model), 'model.json')
    ->post($url);


dd([
    'status' => $resp->status(),
    'headers' => $resp->headers(),
    'body' => $resp->body(),
]);

        if (!$resp->successful()) {
            Log::error('UgPass CryptoQR embed failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return ['success' => false, 'status' => $resp->status(), 'body' => $resp->body()];
        }

        $json = $resp->json();
        
        return ['success' => true, 'data' => $json];
    }
}
