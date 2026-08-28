<?php

namespace App\Services\Scan\Dependencies;

class ComposerLockParser
{
    /**
     * Parse composer.lock JSON and return normalized dependencies.
     *
     * @param string $jsonContent
     * @return array Array of ['ecosystem' => 'Packagist', 'package' => 'name', 'version' => 'version']
     */
    public function parse(string $jsonContent): array
    {
        $dependencies = [];
        $decoded = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        $packages = array_merge(
            $decoded['packages'] ?? [],
            $decoded['packages-dev'] ?? []
        );

        foreach ($packages as $pkg) {
            if (!isset($pkg['name']) || !isset($pkg['version'])) {
                continue;
            }

            $version = ltrim($pkg['version'], 'v');
            
            $key = $pkg['name'] . '@' . $version;
            $dependencies[$key] = [
                'ecosystem' => 'Packagist',
                'package' => $pkg['name'],
                'version' => $version,
            ];
        }

        return array_values($dependencies);
    }
}
