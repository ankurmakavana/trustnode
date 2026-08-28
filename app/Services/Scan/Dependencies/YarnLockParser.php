<?php

namespace App\Services\Scan\Dependencies;

class YarnLockParser
{
    /**
     * Parses a yarn.lock (v1) file and returns normalized dependencies.
     *
     * Unsupported: Yarn Berry (v2+) valid YAML format.
     * Aliases are naturally bypassed or may fall into the same capture group;
     * if they have unusual formatting, they might be skipped safely.
     */
    public function parse(string $content): array
    {
        // Yarn Berry check (very naive, but Berry starts with __metadata:)
        if (str_contains($content, '__metadata:')) {
            // Unsupported: Yarn Berry v2+
            return [];
        }

        $dependencies = [];
        $seen = []; // For deduplication

        // Regex to capture standard Yarn v1 block headers and their immediate version line.
        // Example block:
        // "@babel/code-frame@^7.0.0", "@babel/code-frame@^7.12.11":
        //   version "7.12.11"
        $pattern = '/^"?((?:@[^\/]+\/)?[^@\n"]+)@[^:\n]+:\r?\n\s+version\s+"([^"]+)"/m';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $package = trim($match[1], '"');
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
