<?php

// app/Services/UgPassCryptoQrService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgPassCryptoQrService
{
    public function embedQr(string $pdfPath, array $coords, array $qrData): array
    {
        $url = config('services.ugpass.embed_qr'); // {Signing Base URL}/Agent/api/digital/signature/post/embed/qr
        $tokens = session('ugpass');

        if (!$tokens || empty($tokens['access_token'])) {
            return ['success' => false, 'message' => 'No UgPass access token found. Please login again.'];
        }

        // Spec: multipart with parts "file" and "model"
        // model.qrPlaceHolderCoordinates: pageNumber, signatureXaxis, signatureYaxis, imageWidth, imageHeight
        // model.qrData: publicData, privateData, optional photo (base64, <= ~100KB)
        $model = [
            'qrPlaceHolderCoordinates' => [
                'pageNumber'     => $coords['pageNumber'] ?? 1,
                'signatureXaxis' => $coords['x'] ?? 300,
                'signatureYaxis' => $coords['y'] ?? 400,
                'imageWidth'     => $coords['width'] ?? 120,   // pixels or units per Agent docs
                'imageHeight'    => $coords['height'] ?? 120,
            ],
            'qrData' => [
                'publicData'  => $qrData['publicData'] ?? '{}',
                'privateData' => $qrData['privateData'] ?? '{}',
                // Optional base64 JPEG portrait if face auth is used:
                // 'photo' => $qrData['photo'] ?? null,
            ],
        ];

        $resp = Http::withHeaders([
                'UgPassAuthorization' => 'Bearer '.$tokens['access_token'],
                'Accept' => 'application/json',
            ])
            ->asMultipart()
            ->attach('file', fopen($pdfPath, 'r'), basename($pdfPath))
            ->attach('model', json_encode($model))
            ->post($url);

        if (!$resp->successful()) {
            Log::error('UgPass CryptoQR embed failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return ['success' => false, 'status' => $resp->status(), 'body' => $resp->body()];
        }

        // Response: base64-encoded PDF with embedded QR in result
        $json = $resp->json();
        return ['success' => true, 'data' => $json];
    }
}
