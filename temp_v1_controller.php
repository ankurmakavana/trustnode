<?php

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
