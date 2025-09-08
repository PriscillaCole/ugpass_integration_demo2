<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

class UgpassService
{
    public function buildRequestJwt(string $clientId, string $redirectUri, string $aud, string $state, string $nonce): string
    {
        $now = time();
        $payload = [
            'iss' => $clientId,
            'sub' => $clientId,                
            'aud' => $aud,                    
            'iat' => $now,
            'exp' => $now + 600,
            'jti' => uniqid(),                
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => config('services.ugpass.scope'),
            'state' => $state,
            'nonce' => $nonce,
        ];

        
         $privateKey = file_get_contents(storage_path('keys/ugpass_private.pem'));

        return JWT::encode($payload, $privateKey, 'RS256');
    }


    public function buildClientAssertion(string $clientId, string $aud): string
    {
        $now = time();
        $payload = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $aud,
            'iat' => $now,
            'exp' => $now + 3600,
            'jti' => Uuid::uuid4()->toString(),
        ];
        
        $privateKey = file_get_contents(storage_path('keys/ugpass_private.pem'));
        return JWT::encode($payload, $privateKey, 'RS256');
    }
}
