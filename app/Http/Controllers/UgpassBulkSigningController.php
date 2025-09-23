<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UgpassBulkSigningService;

class UgpassBulkSigningController extends Controller
{
    public function bulkSignRequest(Request $request, UgpassBulkSigningService $service)
{
    $request->validate([
        'documents.*' => 'required|mimes:pdf|max:5120',
    ]);

    $localFiles = [];
    foreach ($request->file('documents') as $doc) {
        $path = $doc->store('uploads', 'public');
        $localFiles[] = storage_path("app/public/{$path}");
    }

    $result = $service->bulkSign(
        $localFiles,
        session('ugpass_user')['daes_claims']['email'] ?? 'unknown@example.com'
    );

    return back()->with(['result' => $result]);
}

}
