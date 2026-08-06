<?php

namespace App\Http\Requests\Finding;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'cve' => ['nullable', 'string', 'regex:/^CVE-\d{4}-\d{4,7}$/'],
            'cvss_score' => ['nullable', 'numeric', 'min:0', 'max:10.0'],
            'severity' => ['required', new Enum(FindingSeverity::class)],
            'status' => ['required', new Enum(FindingStatus::class)],
            'category' => ['required', 'string', 'max:255'],
            'cwe' => ['nullable', 'string', 'regex:/^CWE-\d+$/'],
            'description' => ['nullable', 'string'],
            'technical_details' => ['nullable', 'string'],
            'business_impact' => ['nullable', 'string'],
            'remediation' => ['nullable', 'string'],
            'evidence' => ['nullable', 'string'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'target_id' => ['nullable', 'exists:targets,id'],
            'scan_id' => ['nullable', 'exists:scans,id'],
            'assigned_analyst' => ['nullable', 'exists:users,id'],
        ];
    }
}
