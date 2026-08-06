<?php

namespace App\DTO\Finding;

use App\Http\Requests\Finding\StoreFindingRequest;
use App\Http\Requests\Finding\UpdateFindingRequest;

class FindingData
{
    public function __construct(
        public string $title,
        public ?string $cve,
        public ?float $cvss_score,
        public string $severity,
        public string $status,
        public string $category,
        public ?string $cwe,
        public ?string $description,
        public ?string $technical_details,
        public ?string $business_impact,
        public ?string $remediation,
        public ?string $evidence,
        public ?int $asset_id,
        public ?int $target_id,
        public ?int $scan_id,
        public ?int $assigned_analyst
    ) {}

    public static function fromRequest(StoreFindingRequest|UpdateFindingRequest $request): self
    {
        return new self(
            title: $request->input('title'),
            cve: $request->input('cve'),
            cvss_score: $request->input('cvss_score') !== null ? (float) $request->input('cvss_score') : null,
            severity: $request->input('severity'),
            status: $request->input('status', 'open'),
            category: $request->input('category'),
            cwe: $request->input('cwe'),
            description: $request->input('description'),
            technical_details: $request->input('technical_details'),
            business_impact: $request->input('business_impact'),
            remediation: $request->input('remediation'),
            evidence: $request->input('evidence'),
            asset_id: $request->input('asset_id') !== null ? (int) $request->input('asset_id') : null,
            target_id: $request->input('target_id') !== null ? (int) $request->input('target_id') : null,
            scan_id: $request->input('scan_id') !== null ? (int) $request->input('scan_id') : null,
            assigned_analyst: $request->input('assigned_analyst') !== null ? (int) $request->input('assigned_analyst') : null
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'cve' => $this->cve,
            'cvss_score' => $this->cvss_score,
            'severity' => $this->severity,
            'status' => $this->status,
            'category' => $this->category,
            'cwe' => $this->cwe,
            'description' => $this->description,
            'technical_details' => $this->technical_details,
            'business_impact' => $this->business_impact,
            'remediation' => $this->remediation,
            'evidence' => $this->evidence,
            'asset_id' => $this->asset_id,
            'target_id' => $this->target_id,
            'scan_id' => $this->scan_id,
            'assigned_analyst' => $this->assigned_analyst,
        ];
    }
}
