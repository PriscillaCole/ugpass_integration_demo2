<?php

namespace App\Http\Controllers;

use App\Services\UgpassService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UgpassController extends Controller
{
    public function __construct(private UgpassService $svc) {}
    // Step 1: Redirect to UgPass for authentication

    public function start(Request $request)
    {
       $state = bin2hex(random_bytes(8));
        $nonce = bin2hex(random_bytes(8));
        session(['ugpass_state' => $state, 'ugpass_nonce' => $nonce]);

        $clientId = config('services.ugpass.client_id');
        $redirect = config('services.ugpass.redirect_uri');
        $aud = 'https://stgapi.ugpass.go.ug/idp';  // correct audience value

        $requestJwt = $this->svc->buildRequestJwt($clientId, $redirect, $aud, $state, $nonce);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => config('services.ugpass.scope'),
            'state' => $state,
            'nonce' => $nonce,
            'request' => $requestJwt,
        ]);

        return redirect(config('services.ugpass.authorization') . '?' . $query);

    }

    public function callback(Request $request)
    {
        // Verify state
        $state = $request->get('state');
        if ($state !== session('ugpass_state')) {
            abort(400, 'Invalid state');
        }

        $code = $request->get('code');
        if (!$code) {
            return response()->json([
                'error' => $request->get('error'),
                'description' => $request->get('error_description')
            ], 400);
        }

        $clientId = config('services.ugpass.client_id');
        $redirect = config('services.ugpass.redirect_uri');

        // Build client assertion JWT
        $clientAssertion = $this->svc->buildClientAssertion(
            $clientId,
            config('services.ugpass.token')
        );

        // Exchange code for tokens
        $tokenRes = Http::asForm()->post(config('services.ugpass.token'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
            'client_id' => $clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $clientAssertion,
        ]);

        if (!$tokenRes->ok()) {
            return response()->json($tokenRes->json(), $tokenRes->status());
        }

        $tokens = $tokenRes->json();


        if (!isset($tokens['id_token'])) {
        return response()->json([
            'error' => 'UgPass did not return an id_token',
            'response' => $tokens,
        ], 400);
        }

        $idToken = $tokens['id_token'];

        $claims = $this->decodeIdToken($idToken);

        session([
            'ugpass' => $tokens,
            'ugpass_user' => $claims,
        ]);
       
        return redirect()->route('dashboard');
    }
  
    private function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return [];
        }
        return json_decode(base64_decode($parts[1]), true) ?? [];
    }


    public function logout(Request $request)
    {
        // get ID token from session (or wherever you stored it)
        $tokens = session('ugpass');
        $idToken = $tokens['id_token'] ?? null;

        if (!$idToken) {
            return redirect('/')->withErrors('No active UgPass session');
        }

        $url = config('services.ugpass.logout') . '?' . http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => config('app.url') . '/logged-out',
            'state' => bin2hex(random_bytes(8)),
        ]);

        // clear local session
        session()->forget(['ugpass', 'ugpass_user']);

        return redirect($url);
    }

}
