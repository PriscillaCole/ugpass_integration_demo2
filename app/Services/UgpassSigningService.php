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
 private function saveSignedDocument(string $base64, string $originalName): string
{
    $dir = storage_path('app/signed');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filePath = $dir . '/' . pathinfo($originalName, PATHINFO_FILENAME) . '-signed.pdf';
    file_put_contents($filePath, base64_decode($base64));

    return $filePath;
}

public function signDocument(string $filePath, string $userEmail)
{
    $url = config('services.ugpass.sign');
    $clientId = config('services.ugpass.client_id');

    // 1. Hash the PDF
    $hash = base64_encode(hash('sha256', file_get_contents($filePath), true));

    // 2. Create client assertion JWT
    $clientAssertion = $this->buildClientAssertion($url);

    // 3. Call UgPass signing API
    $res = Http::post($url, [
        'client_id' => $clientId,
        'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        'client_assertion' => $clientAssertion,
        'hash' => $hash,
        'hashAlgorithm' => 'SHA256',
        'signingType' => 'PADES',
        'documentName' => basename($filePath),
        'userEmail' => $userEmail,
    ]);

    // 4. Debug raw response
    if (!$res->ok()) {
        dd("UgPass error", $res->status(), $res->body());
    }

    $json = $res->json();

    // 5. Save signed PDF
    if (isset($json['signedDocument'])) {
        $dir = storage_path('app/signed');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePathSigned = $dir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '-signed.pdf';
        file_put_contents($filePathSigned, base64_decode($json['signedDocument']));

        $json['savedPath'] = $filePathSigned;
    }

    return $json;
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
