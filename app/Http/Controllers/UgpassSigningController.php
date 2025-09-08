<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UgPassService;

class UgpassSigningController extends Controller
{
    protected $service;

    public function __construct(UgPassService $service)
    {
        $this->service = $service;
    }

    /** Single document sign */
    public function signDocument()
    {
        $tokens = session('ugpass');
        if (!$tokens) {
            return redirect()->route('login');
        }

        $accessToken = $tokens['access_token'];
        $result = $this->service->signDocument(
            $accessToken,
            storage_path('app/test.pdf'),
            "user@example.com"
        );

        return response()->json($result);
    }

    /** Bulk sign */
    public function bulkSign()
    {
        $tokens = session('ugpass');
        if (!$tokens) {
            return redirect()->route('login');
        }

        $accessToken = $tokens['access_token'];
        $result = $this->service->bulkSign(
            $accessToken,
            "C:/docs/to-sign",
            "C:/docs/signed-output",
            "user@example.com"
        );

        return response()->json($result);
    }

    /** Bulk signing status */
    public function bulkStatus($id)
    {
        $tokens = session('ugpass');
        if (!$tokens) {
            return redirect()->route('login');
        }

        $accessToken = $tokens['access_token'];
        $result = $this->service->bulkSignStatus($accessToken, $id);

        return response()->json($result);
    }

    /** Embed Crypto QR */
    public function embedQr()
    {
        $tokens = session('ugpass');
        if (!$tokens) {
            return redirect()->route('login');
        }

        $accessToken = $tokens['access_token'];
        $result = $this->service->embedCryptoQR(
            $accessToken,
            storage_path('app/test.pdf'),
            json_encode(["invoiceNo" => "12345", "date" => "2025-09-08"]),
            json_encode(["secretCode" => "ABC123"])
        );

        return response()->json($result);
    }
}
