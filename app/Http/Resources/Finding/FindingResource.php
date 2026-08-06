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
            'category' => $this->category,
            'cwe' => $this->cwe,
            'description' => $this->description,
            'technical_details' => $this->technical_details,
            'business_impact' => $this->business_impact,
            'remediation' => $this->remediation,
            'evidence' => $this->evidence,

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
}
