<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;

class UgpassSigningService
{
    private function buildClientAssertion(string $aud): string
    {
        $now = time();
        $clientId = config('services.ugpass.client_id');

        $payload = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $aud,
            'iat' => $now,
            'exp' => $now + 600,
            'jti' => Uuid::uuid4()->toString(),
        ];

        $privateKey = file_get_contents(storage_path('keys/ugpass_private.pem'));

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /** Single Document Sign */
/** Single Document Sign */
/** Single Document Sign */
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
        'placeHolderCoordinates' => null,
        'esealPlaceHolderCoordinates' => null,
    ];

    try {
       $response = Http::withHeaders([
    'Authorization' => "Bearer {$accessToken}",       // UgPass token
    'UgPassAuthorization' => "Bearer {$accessToken}", // duplicate for compatibility
    'Accept' => 'application/json',
        ])
        ->attach('multipartFile', file_get_contents($filePath), basename($filePath))
        ->asMultipart()
        ->post($url, [
            ['name' => 'model', 'contents' => json_encode($model)]
        ]);


        if (!$response->successful()) {
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

    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Request failed: '.$e->getMessage(),
        ];
    }
}







public function bulkSign(array $files, string $userEmail)
{
    $url = config('services.ugpass.bulk_sign');
    $clientId = config('services.ugpass.client_id');

    $documents = [];
    foreach ($files as $filePath) {
        $documents[] = [
            'documentName' => basename($filePath),
            'hash' => base64_encode(hash('sha256', file_get_contents($filePath), true)),
            'hashAlgorithm' => 'SHA256',
        ];
    }

    $clientAssertion = $this->buildClientAssertion($url);

    $res = Http::post($url, [
        'client_id' => $clientId,
        'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        'client_assertion' => $clientAssertion,
        'documents' => $documents,
        'signingType' => 'PADES',
        'userEmail' => $userEmail,
    ]);

    $json = $res->json();

    // If API returns signed documents directly (depends on UgPass config)
    if (isset($json['documents'])) {
        foreach ($json['documents'] as &$doc) {
            if (isset($doc['signedDocument'])) {
                $doc['savedPath'] = $this->saveSignedDocument(
                    $doc['signedDocument'],
                    $doc['documentName']
                );
            }
        }
    }

    return $json;
    }

    // Save signed document to storage and return the path
    private function saveSignedDocument(string $base64Content, string $originalFileName): string
    {
        $dir = storage_path('app/signed');
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filePathSigned = $dir.'/'.pathinfo($originalFileName, PATHINFO_FILENAME).'-signed.pdf';
        file_put_contents($filePathSigned, base64_decode($base64Content));
        return $filePathSigned;
    }
    // Embed Crypto QR Code into document
    

    public function embedCryptoQR(string $filePath, array $metaData, array $secretData)
    {
        $url = config('services.ugpass.qr');
        $clientId = config('services.ugpass.client_id');

        $hash = base64_encode(hash('sha256', file_get_contents($filePath), true));
        $clientAssertion = $this->buildClientAssertion($url);

        $res = Http::post($url, [
            'client_id' => $clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $clientAssertion,
            'documentName' => basename($filePath),
            'hash' => $hash,
            'hashAlgorithm' => 'SHA256',
            'metaData' => $metaData,
            'secretData' => $secretData,
        ]);

        $json = $res->json();

        if (isset($json['signedDocument'])) {
            $json['savedPath'] = $this->saveSignedDocument($json['signedDocument'], basename($filePath));
        }

        return $json;
    }

}
