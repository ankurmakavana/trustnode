<?php

namespace App\Http\Requests\CodebaseProject;

use App\Enums\CodebaseProject\ScanProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCodebaseProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('targets.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get user's org id dynamically for uniqueness check
        $userId = $this->user()->id;
        $orgId = \App\Models\Team::query()
            ->whereHas('users', fn($q) => $q->whereKey($userId))
            ->value('organization_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'path' => [
                'required', 
                'string', 
                'max:512',
                // Custom rule added in controller to validate path resolves in workspace
                Rule::unique('codebase_projects', 'path')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
            ],
            'source_type' => ['nullable', 'string', Rule::in(['workspace_mount', 'agent'])],
            'agent_id' => ['nullable', 'string', 'max:128'],
            'scan_profile' => ['required', 'string', Rule::enum(ScanProfile::class)],
            'continuous_scan_enabled' => ['required', 'boolean'],
        ];
    }
}
