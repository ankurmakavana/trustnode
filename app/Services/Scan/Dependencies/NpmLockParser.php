<?php

namespace App\Services\Scan\Dependencies;

class NpmLockParser
{
    /**
     * Parse package-lock.json JSON and return normalized dependencies.
     *
     * @param string $jsonContent
     * @return array Array of ['ecosystem' => 'npm', 'package' => 'name', 'version' => 'version']
     */
    public function parse(string $jsonContent): array
    {
        $dependencies = [];
        $decoded = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        $lockfileVersion = $decoded['lockfileVersion'] ?? 1;

        if ($lockfileVersion >= 2 && isset($decoded['packages']) && is_array($decoded['packages'])) {
            // Lockfile version 2 & 3
            foreach ($decoded['packages'] as $path => $pkg) {
                // The root project is usually "" (empty string). We can skip it.
                if (empty($path)) {
                    continue;
                }

                // In lockfile v2/v3, name is either explicitly in the node or derived from path
                $name = $pkg['name'] ?? null;
                if (!$name) {
                    $parts = explode('node_modules/', $path);
                    $name = end($parts);
                }

                if (!empty($name) && !empty($pkg['version'])) {
                    $key = $name . '@' . $pkg['version'];
                    $dependencies[$key] = [
                        'ecosystem' => 'npm',
                        'package' => $name,
                        'version' => $pkg['version'],
                    ];
                }
            }
        } elseif (isset($decoded['dependencies']) && is_array($decoded['dependencies'])) {
            // Lockfile version 1
            $this->parseV1Dependencies($decoded['dependencies'], $dependencies);
        }

        return array_values($dependencies);
    }

    protected function parseV1Dependencies(array $deps, array &$dependencies): void
    {
        foreach ($deps as $name => $pkg) {
            if (!empty($pkg['version'])) {
                $key = $name . '@' . $pkg['version'];
                $dependencies[$key] = [
                    'ecosystem' => 'npm',
                    'package' => $name,
                    'version' => $pkg['version'],
                ];
            }

            if (isset($pkg['dependencies']) && is_array($pkg['dependencies'])) {
                $this->parseV1Dependencies($pkg['dependencies'], $dependencies);
            }
        }
    }
}
