<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgpassSigningService
{
 
   // Single Document Sign
public function signDocument(string $filePath, string $userEmail)
{
    $url = config('services.ugpass.sign');

    $tokens = session('ugpass');
    
    
    if (!$tokens || !isset($tokens['access_token'])) {
        return [
            'success' => false,
            'message' => 'No UgPass access token found. Please login again.'
        ];
    }
    $accessToken = $tokens['access_token'];

    $model = [
        'documentType' => 'PADES',
        'id' => $userEmail,
        'placeHolderCoordinates' => [
            'pageNumber' => 1,
            'signatureXaxis' => 300.00,
            'signatureYaxis' => 400.00
        ],
        'esealPlaceHolderCoordinates' => null,
    ];
  

    try {
       
        $response = Http::withHeaders([
                'UgPassAuthorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ])
            ->asMultipart()
            ->attach('multipartFile', file_get_contents($filePath), basename($filePath)) 
            ->attach('model', json_encode($model))
            ->post($url);
        dd($response->body(), $model);

        if (!$response->successful()) {
            dd('not successful');
            Log::error('UgPass signing failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'UgPass API returned an error.',
                'status'  => $response->status(),
                'body'    => $response->body(),
            ];
        }

        $json = $response->json();
        if (isset($json['result'])) {
            $dir = storage_path('app/signed');
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $filePathSigned = $dir.'/'.pathinfo($filePath, PATHINFO_FILENAME).'-signed.pdf';
            file_put_contents($filePathSigned, base64_decode($json['result']));
            $json['savedPath'] = $filePathSigned;
        }

        return $json;

    } catch (\Throwable $e) {
        Log::error('Exception in signDocument', [
            'exception' => $e,
            'file'      => $filePath,
            'userEmail' => $userEmail,
        ]);

        return [
            'success' => false,
            'message' => 'Request failed: '.$e->getMessage(),
        ];
    }
}


   
}
