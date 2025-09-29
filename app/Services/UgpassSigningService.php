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
                ->timeout(60) 
                ->post($url);


            if (!$response->successful()) {
                Log::error('UgPass signing failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'UgPass API returned an error.',
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ];
            }

            $json = $response->json();
            Log::info("UgPass API response", $json);

            // Ensure success flag is true
            if (!isset($json['success']) || !in_array($json['success'], [true, "true"], true)) {
                return [
                    'success' => false,
                    'message' => 'UgPass API did not return success.',
                    'body'    => $json,
                ];
            }

            // Ensure result exists
            if (isset($json['result']) && !empty($json['result'])) {
                $dir = storage_path('app/signed');
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $filePathSigned = $dir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '-signed.pdf';

                // Decode safely
                $content = base64_decode($json['result'], true);
                if ($content === false) {
                    Log::error("Base64 decoding failed", [
                        'result_sample' => substr($json['result'], 0, 50)
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Base64 decoding failed.',
                    ];
                }

                file_put_contents($filePathSigned, $content);

                if (file_exists($filePathSigned)) {
                    Log::info("Signed file saved successfully", ['path' => $filePathSigned]);
                    $json['savedPath'] = $filePathSigned;
                } else {
                    Log::error("Failed to save signed file", ['path' => $filePathSigned]);
                    $json['savedPath'] = null;
                }
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
                'message' => 'Request failed: ' . $e->getMessage(),
            ];
        }
    }

   

}
