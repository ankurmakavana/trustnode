<?php
$path = 'c:\\xampp\\htdocs\\trustnode-license-platform\\app\\Http\\Controllers\\Api\\V1\\ReleaseController.php';
$content = file_get_contents($path);
$newMethod = <<<EOT
    public function coreLatest(Request \$request)
    {
        \$currentVersion = \$request->query('current_version', '0.0.0');
        \$product = \App\Models\Product::where('slug', 'trustnode-core')->first();
        
        if (!\$product) {
            return response()->json([
                'available' => false,
                'current_version' => \$currentVersion,
                'version' => null
            ]);
        }

        \$release = Release::where('product_id', \$product->id)
            ->where('is_active', true)
            ->where('is_latest', true)
            ->first();

        if (!\$release) {
            return response()->json([
                'available' => false,
                'current_version' => \$currentVersion,
                'version' => null
            ]);
        }

        if (version_compare(\$currentVersion, \$release->version, '>=')) {
            return response()->json([
                'available' => false,
                'current_version' => \$currentVersion,
                'version' => \$release->version
            ]);
        }

        \$expiresAt = now()->addMinutes(15);
        \$downloadUrl = URL::temporarySignedRoute(
            'api.v1.releases.download', 
            \$expiresAt, 
            ['release' => \$release->id]
        );

        return response()->json([
            'available' => true,
            'current_version' => \$currentVersion,
            'version' => \$release->version,
            'sha256' => \$release->checksum,
            'download_url' => \$downloadUrl,
            'expires_at' => \$expiresAt->toIso8601String()
        ]);
    }
EOT;
$content = preg_replace('/public function coreLatest\(Request \$request\).*?(?=public function download)/s', $newMethod . "\n\n    ", $content);
file_put_contents($path, $content);
echo "Done.\n";
