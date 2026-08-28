<?php

namespace App\Services\Scan\Dependencies;

class PnpmLockParser
{
    /**
     * Parses a pnpm-lock.yaml file and returns normalized dependencies.
     * Supported formats: v5 ( /package/1.0.0 ) and v6/v9 ( package@1.0.0 )
     * Correctly strips peer dependency suffixes e.g. package@1.0.0(peer@2.0.0)
     */
    public function parse(string $content): array
    {
        $dependencies = [];
        $seen = [];

        // Regex captures lines with 2 spaces indent, optional quote, optional slash,
        // package name (handling scopes), separator (@ or /), version (semver),
        // optional peer dependency block in parentheses, optional quote, and colon.
        $pattern = '/^  \'?\/?((?:@[^\/]+\/)?[^@\/\n\']+)[@\/]([0-9]+\.[0-9]+\.[0-9]+(?:-[a-zA-Z0-9.-]+)?(?:\+[a-zA-Z0-9.-]+)?)(?:\([^\)]+\))?\'?:/m';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $package = trim($match[1], "'\"");
                $version = trim($match[2]);

                $key = $package . '@' . $version;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $dependencies[] = [
                        'ecosystem' => 'npm',
                        'package' => $package,
                        'version' => $version,
                    ];
                }
            }
        }

        return $dependencies;
    }
}
