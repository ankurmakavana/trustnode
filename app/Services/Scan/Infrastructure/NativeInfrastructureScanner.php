<?php

namespace App\Services\Scan\Infrastructure;

use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Facades\Log;

class NativeInfrastructureScanner
{
    /**
     * Common ports to check.
     * We don't do full 1-65535 to prevent abuse and timeout, just top ports.
     */
    protected array $commonPorts = [
        21, 22, 23, 25, 53, 80, 110, 143, 443, 3306, 3389, 5432, 8080, 8443
    ];

    /**
     * Scan the target using native PHP networking.
     * @return array<NormalizedFinding>
     */
    public function scan(ValidatedTarget $target): array
    {
        Log::info("NativeInfrastructureScanner starting scan for target: {$target->original} (IP: {$target->ip})");
        
        $findings = [];

        // 1. Port Discovery
        // Connect directly to the IP to avoid DNS rebinding on port discovery.
        $openPorts = $this->discoverPorts($target->ip);
        if (!empty($openPorts)) {
            $findings[] = $this->createPortFinding($target->original, $openPorts);
        }

        // 2. HTTP/HTTPS Security Headers & TLS check (only if 80/443 or specific ports open)
        if (in_array(443, $openPorts) || in_array(8443, $openPorts)) {
            $port = in_array(443, $openPorts) ? 443 : 8443;
            $tlsFindings = $this->checkTls($target, $port);
            $findings = array_merge($findings, $tlsFindings);
        }

        if (in_array(80, $openPorts) || in_array(443, $openPorts)) {
            $scheme = in_array(443, $openPorts) ? 'https' : 'http';
            $port = in_array(443, $openPorts) ? 443 : 80;
            $httpFindings = $this->checkHttp($target, $scheme, $port);
            $findings = array_merge($findings, $httpFindings);
        }

        return $findings;
    }

    protected function discoverPorts(string $ip): array
    {
        $openPorts = [];
        foreach ($this->commonPorts as $port) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 1.5);
            if (is_resource($connection)) {
                $openPorts[] = $port;
                fclose($connection);
            }
        }
        return $openPorts;
    }

    protected function createPortFinding(string $targetOriginal, array $openPorts): NormalizedFinding
    {
        $portsList = implode(', ', $openPorts);
        return new NormalizedFinding([
            'scanner' => 'NativeInfrastructureScanner',
            'scannerRuleId' => 'INFRA-PORT-001',
            'title' => 'Open Ports Detected',
            'severity' => 'info',
            'category' => 'Network',
            'description' => "The following ports were found open on the target: {$portsList}",
            'remediation' => 'Review open ports and ensure only necessary services are exposed to the public.',
            'technicalDetails' => "Ports: {$portsList}",
            'evidence' => "Successfully connected to ports via TCP handshake.",
            'url' => $targetOriginal,
            'assetIdentifier' => $targetOriginal
        ]);
    }

    protected function checkTls(ValidatedTarget $target, int $port): array
    {
        $findings = [];
        
        $contextOptions = [
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        // Mitigate DNS Rebinding: Connect to IP, but set SNI/Peer name to domain
        if ($target->isDomain) {
            $contextOptions['ssl']['peer_name'] = $target->original;
            $contextOptions['ssl']['SNI_server_name'] = $target->original;
        }

        $streamContext = stream_context_create($contextOptions);

        $client = @stream_socket_client(
            "ssl://{$target->ip}:{$port}",
            $errno,
            $errstr,
            3.0,
            STREAM_CLIENT_CONNECT,
            $streamContext
        );

        if ($client) {
            $params = stream_context_get_params($client);
            if (isset($params['options']['ssl']['peer_certificate'])) {
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                if ($cert) {
                    $validTo = $cert['validTo_time_t'] ?? 0;
                    if ($validTo < time()) {
                        $findings[] = new NormalizedFinding([
                            'scanner' => 'NativeInfrastructureScanner',
                            'scannerRuleId' => 'INFRA-TLS-001',
                            'title' => 'Expired TLS/SSL Certificate',
                            'severity' => 'high',
                            'category' => 'Cryptography',
                            'description' => 'The target is using an expired TLS certificate.',
                            'remediation' => 'Renew and deploy a valid TLS certificate.',
                            'technicalDetails' => 'Expired on: ' . date('Y-m-d H:i:s', $validTo),
                            'evidence' => json_encode(['validTo' => $validTo]),
                            'url' => "https://{$target->original}:{$port}",
                            'assetIdentifier' => $target->original
                        ]);
                    }
                }
            }
            fclose($client);
        }

        return $findings;
    }

    protected function checkHttp(ValidatedTarget $target, string $scheme, int $port): array
    {
        $findings = [];
        
        $url = "{$scheme}://{$target->original}:{$port}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Do NOT follow redirects automatically to prevent SSRF bypass

        // Mitigate DNS Rebinding: Force curl to use the validated IP
        if ($target->isDomain) {
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$target->original}:{$port}:{$target->ip}"]);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            return $findings;
        }

        $headers = substr($response, 0, $headerSize);
        $headerLines = explode("\r\n", $headers);
        $parsedHeaders = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($key))] = trim($value);
            }
        }

        // Check for missing security headers
        $missingHeaders = [];
        if (!isset($parsedHeaders['strict-transport-security']) && $scheme === 'https') {
            $missingHeaders[] = 'Strict-Transport-Security';
        }
        if (!isset($parsedHeaders['x-frame-options']) && !isset($parsedHeaders['content-security-policy'])) {
            $missingHeaders[] = 'X-Frame-Options or CSP frame-ancestors';
        }
        if (!isset($parsedHeaders['x-content-type-options'])) {
            $missingHeaders[] = 'X-Content-Type-Options';
        }

        if (!empty($missingHeaders)) {
            $findings[] = new NormalizedFinding([
                'scanner' => 'NativeInfrastructureScanner',
                'scannerRuleId' => 'INFRA-HTTP-001',
                'title' => 'Missing Security Headers',
                'severity' => 'low',
                'category' => 'Configuration',
                'description' => 'The HTTP response is missing basic security headers.',
                'remediation' => 'Configure the web server to return standard security headers (HSTS, X-Frame-Options, X-Content-Type-Options).',
                'technicalDetails' => 'Missing headers: ' . implode(', ', $missingHeaders),
                'evidence' => $headers,
                'url' => $url,
                'assetIdentifier' => $target->original
            ]);
        }

        return $findings;
    }
}
