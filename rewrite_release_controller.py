import os

content = r"""<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class ReleaseController extends Controller
{
    public function latest(Request $request)
    {
        $license = $request->attributes->get('auth_license');
        if (!$license) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'License not found in request context.']], 403);
        }

        $release = Release::where('product_id', $license->product_id)
            ->where('is_active', true)
            ->where('is_latest', true)
            ->first();

        if (!$release) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'No active release found for this product.']], 404);
        }

        $expiresAt = now()->addMinutes(15);
        $downloadUrl = URL::temporarySignedRoute(
            'api.v1.releases.download',
            $expiresAt,
            ['release' => $release->id]
        );

        return response()->json([
            'version' => $release->version,
            'checksum' => $release->checksum,
            'artifact_size' => $release->artifact_size,
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function coreLatest(Request $request)
    {
        $product = \App\Models\Product::where('slug', 'trustnode-core')->first();
        if (!$product) {
            return response()->json(['available' => false, 'error_code' => 'no_release_available']);
        }
        $release = Release::where('product_id', $product->id)->where('is_active', true)->where('is_latest', true)->first();
        if (!$release) {
            return response()->json(['available' => false, 'error_code' => 'no_release_available']);
        }
        $currentVersion = $request->query('current_version', '0.0.0');
        if (version_compare($currentVersion, $release->version, '>=')) {
            return response()->json(['available' => false, 'error_code' => 'up_to_date', 'current_version' => $currentVersion, 'version' => $release->version]);
        }
        $expiresAt = now()->addMinutes(15);
        $downloadUrl = URL::temporarySignedRoute('api.v1.releases.download', $expiresAt, ['release' => $release->id]);
        return response()->json(['available' => true, 'version' => $release->version, 'current_version' => $currentVersion, 'sha256' => $release->checksum, 'download_url' => $downloadUrl, 'expires_at' => $expiresAt->toIso8601String()]);
    }

    public function download(Request $request, Release $release)
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid or expired signature.');
        }

        if (!$release->is_active) {
            abort(404, 'Release is not active.');
        }

        $path = $release->artifact_path;
        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Artifact file not found on server.');
        }

        return Storage::disk('local')->download($path, $release->artifact_filename);
    }
}
"""

with open(r'c:\xampp\htdocs\trustnode-license-platform\app\Http\Controllers\Api\V1\ReleaseController.php', 'w', encoding='utf-8') as f:
    f.write(content)
