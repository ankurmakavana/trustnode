<?php

namespace App\Http\Resources\Finding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'finding_id' => $this->finding_id,
            'title' => $this->title,
            'cve' => $this->cve,
            'cvss_score' => $this->cvss_score !== null ? (float) $this->cvss_score : null,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'lifecycle_status' => $this->lifecycle_status?->value ?? 'new',
            'category' => $this->category,
            'cwe' => $this->cwe,
            'description' => $this->description,
            'technical_details' => $this->maskSecrets($this->technical_details, $this->category, $this->title),
            'business_impact' => $this->business_impact,
            'remediation' => $this->remediation,
            'evidence' => $this->maskSecrets($this->evidence, $this->category, $this->title),
            'url' => $this->url,
            'scanner' => $this->scanner,

            // Relationships
            'asset' => $this->asset_id ? [
                'id' => $this->asset_id,
                'name' => $this->asset?->name,
                'type' => $this->asset?->type?->value,
                'value' => $this->asset?->value,
            ] : null,
            'target' => $this->target_id ? [
                'id' => $this->target_id,
                'name' => $this->target?->name,
                'type' => $this->target?->type?->value,
                'value' => $this->target?->value,
            ] : null,
            'scan' => $this->scan_id ? [
                'id' => $this->scan_id,
                'name' => $this->scan?->name,
                'type' => $this->scan?->type?->value,
                'status' => $this->scan?->status?->value,
            ] : null,
            'analyst' => $this->assigned_analyst ? [
                'id' => $this->assigned_analyst,
                'name' => $this->analyst?->name,
                'email' => $this->analyst?->email,
            ] : null,
            'creator' => [
                'id' => $this->created_by,
                'name' => $this->creator?->name,
            ],
            'updater' => $this->updated_by ? [
                'id' => $this->updated_by,
                'name' => $this->updater?->name,
            ] : null,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function maskSecrets(?string $content, ?string $category, ?string $title): ?string
    {
        if (empty($content)) {
            return $content;
        }

        if (stripos($category ?? '', 'secret') !== false || stripos($title ?? '', 'secret') !== false || stripos($title ?? '', 'token') !== false) {
            return '******** [REDACTED IN API RESPONSE] ********';
        }

        $pattern = '/((?:api_key|apikey|token|secret|password|passwd|key|auth)(?:\s*[:=]\s*[\'"]?))([^\'"\s]+)([\'"]?)/i';
        return preg_replace($pattern, '$1********$3', $content);
    }
}
