<?php

namespace App\DTOs\Import;

class NormalizedFinding
{
    public readonly ?string $scanner;

    public readonly ?string $scannerPluginId;

    public readonly ?string $scannerOid;

    public readonly ?string $scannerRuleId;

    public readonly ?string $assetIdentifier; // IP or Domain

    public readonly ?string $host;

    public readonly ?string $hostname;

    public readonly ?string $ip;

    public readonly ?int $port;

    public readonly ?string $protocol;

    public readonly ?string $service;

    public readonly ?string $url;

    public readonly ?string $path;

    public readonly ?string $parameter;

    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $severity;

    public readonly ?float $cvss;

    public readonly ?string $cve;

    public readonly ?string $cwe;

    public readonly ?string $references;

    public readonly ?string $evidence;

    public readonly ?string $remediation;

    public readonly ?string $risk;

    public readonly ?string $compliance;

    public readonly array $metadata;

    public readonly mixed $firstSeen;

    public readonly mixed $lastSeen;

    // Additional field to support parser compatibility mapping
    public readonly ?string $category;

    public readonly ?string $technicalDetails;

    public function __construct(array $data = [])
    {
        $this->scanner = $data['scanner'] ?? null;
        $this->scannerPluginId = $data['scannerPluginId'] ?? null;
        $this->scannerOid = $data['scannerOid'] ?? null;
        $this->scannerRuleId = $data['scannerRuleId'] ?? null;
        $this->assetIdentifier = $data['assetIdentifier'] ?? null;
        $this->host = $data['host'] ?? null;
        $this->hostname = $data['hostname'] ?? null;
        $this->ip = $data['ip'] ?? null;
        $this->port = $data['port'] ?? null;
        $this->protocol = $data['protocol'] ?? null;
        $this->service = $data['service'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->path = $data['path'] ?? null;
        $this->parameter = $data['parameter'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->severity = $data['severity'] ?? null;
        $this->cvss = isset($data['cvss']) ? (float) $data['cvss'] : null;
        $this->cve = $data['cve'] ?? null;
        $this->cwe = $data['cwe'] ?? null;
        $this->references = $data['references'] ?? null;
        $this->evidence = $data['evidence'] ?? null;
        $this->remediation = $data['remediation'] ?? null;
        $this->risk = $data['risk'] ?? null;
        $this->compliance = $data['compliance'] ?? null;
        $this->metadata = $data['metadata'] ?? [];
        $this->firstSeen = $data['firstSeen'] ?? null;
        $this->lastSeen = $data['lastSeen'] ?? null;

        $this->category = $data['category'] ?? null;
        $this->technicalDetails = $data['technicalDetails'] ?? null;
    }
}
