<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UgpassBulkSigningService
{
    protected string $bulkSignUrl;
    protected string $inputDisk;
    protected string $signedDisk;

    public function __construct()
    {
        $this->bulkSignUrl = config('services.ugpass.bulk_sign');
        $this->inputDisk = 'sftp-ugpass';         // uploads folder
        $this->signedDisk = 'sftp-ugpass-signed'; // signed docs folder
    }

    /**
     * Bulk sign multiple documents.
     *
     * @param array $localFiles Array of local file paths
     * @param string $userEmail Email of the signer
     * @return array
     */
    public function bulkSign(array $localFiles, string $userEmail): array
    {
      
        $tokens = session('ugpass');
        if (!$tokens || !isset($tokens['access_token'])) {
            return ['success' => false, 'message' => 'No UgPass access token. Please login again.'];
        }

        // 1. Upload files to UgPass input_docs
        $uploadedFiles = [];
        foreach ($localFiles as $filePath) {
            if (!file_exists($filePath)) {
                Log::error("File not found: {$filePath}");
                continue;
            }

            $filename = basename($filePath);
            Storage::disk($this->inputDisk)->put($filename, file_get_contents($filePath));
            $uploadedFiles[] = $filename;

            Log::info("Uploaded file to UgPass input_docs: {$filename}");
        }

        if (empty($uploadedFiles)) {
            return ['success' => false, 'message' => 'No files were uploaded to UgPass.'];
        }

        // 2. Trigger bulk signing
        $model = [
        "sourcePath" => "C:/UgPass/input_docs",
        "destinationPath" => "C:/UgPass/signed_docs",
        "id" => $userEmail,
        "correlationId" => (string) Str::uuid(),
        "placeHolderCoordinates" => [
            "pageNumber" => 1,
            "signatureXaxis" => 300.00,
            "signatureYaxis" => 400.00,
        ],
        "esealPlaceHolderCoordinates" => null,
    ];

    $response = Http::withHeaders([
        'UgPassAuthorization' => "Bearer {$tokens['access_token']}",
        'Accept' => 'application/json',
    ])->asMultipart()->post($this->bulkSignUrl, [
        [
            'name' => 'model',
            'contents' => json_encode($model),
        ]
    ]);

    dd($response->body());


        if (!$response->successful()) {
            Log::error("UgPass bulk signing failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'UgPass bulk API returned an error.',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $json = $response->json();

        // 3. Retrieve signed files from UgPass signed_docs
        $savedFiles = [];
        foreach ($uploadedFiles as $filename) {
            if (Storage::disk($this->signedDisk)->exists($filename)) {
                $fileContent = Storage::disk($this->signedDisk)->get($filename);
                $localSignedPath = storage_path("app/signed/{$filename}");
                file_put_contents($localSignedPath, $fileContent);
                $savedFiles[] = $localSignedPath;

                Log::info("Retrieved signed file: {$filename}");
            } else {
                Log::warning("Signed file not found on UgPass server: {$filename}");
            }
        }

        $json['savedFiles'] = $savedFiles;

        return $json;
    }
}
