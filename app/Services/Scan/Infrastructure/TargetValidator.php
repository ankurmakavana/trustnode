<?php

namespace App\Services\Scan\Infrastructure;

class TargetValidator
{
    /**
     * Validate the given target. Ensure it's a safe IPv4, IPv6, or domain name.
     * Resolves the domain to an IP and validates the resolved IP to prevent SSRF and DNS rebinding.
     * Throws \InvalidArgumentException if invalid or unsafe.
     */
    public function validate(string $target): ValidatedTarget
    {
        $target = trim($target);
        
        // Strip protocol if provided
        if (preg_match('#^https?://#i', $target)) {
            $parsed = parse_url($target);
            $target = $parsed['host'] ?? $target;
        } else {
            // If no protocol, it might be an IPv6 address, IPv4, or domain, possibly with a port or path
            // Let's manually strip path and credentials
            $target = preg_replace('#/.*$#', '', $target); // strip path
            $target = preg_replace('#^[^@]+@#', '', $target); // strip credentials
            
            // If it's an IPv6 without brackets but has a port, it's ambiguous. 
            // We assume valid IPv6 addresses are handled. If it has brackets [::1]:80, we extract it.
            if (preg_match('/^\[(.*?)\](:\d+)?$/', $target, $matches)) {
                $target = $matches[1];
            } else {
                // Not in brackets. If it's a valid IPv6 as-is, keep it. 
                // If not, we might need to strip a port (e.g. example.com:8080 or 1.1.1.1:80)
                if (!filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $target = preg_replace('/:[0-9]+$/', '', $target);
                }
            }
        }
        
        // Strip IPv6 brackets if still present
        $target = trim($target, '[]');

        if (empty($target)) {
            throw new \InvalidArgumentException('Target cannot be empty.');
        }

        if (in_array(strtolower($target), ['localhost', '127.0.0.1', '::1'])) {
            throw new \InvalidArgumentException('Localhost scanning is not permitted.');
        }

        if ($this->isValidIp($target)) {
            $this->checkPrivateIp($target);
            return new ValidatedTarget($target, $target, false);
        }

        if ($this->isValidDomain($target)) {
            // Resolve DNS
            $ip = $this->resolveDns($target);
            if ($ip === $target) {
                throw new \InvalidArgumentException("Could not resolve domain: {$target}");
            }
            
            // Check if the resolved IP is safe
            $this->checkPrivateIp($ip);
            
            return new ValidatedTarget($target, $ip, true);
        }

        throw new \InvalidArgumentException('Invalid target format. Must be a valid IP address or domain name.');
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function isValidDomain(string $domain): bool
    {
        // Must contain at least one dot, no spaces, valid characters
        return preg_match('/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $domain) === 1;
    }

    protected function checkPrivateIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE) === false) {
            throw new \InvalidArgumentException("Target resolves to a private, reserved, or loopback IP address ({$ip}), which is not permitted.");
        }
    }

    protected function resolveDns(string $domain): string
    {
        return gethostbyname($domain);
    }
}
