<?php

namespace App\Http\Requests\Target;

use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handled by policy gates
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TargetType::class)],
            'value' => ['required', 'string', 'max:2048'],
            'environment' => ['required', Rule::enum(TargetEnvironment::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'criticality' => ['nullable', Rule::enum(TargetCriticality::class)],
            'status' => ['nullable', Rule::enum(TargetStatus::class)],
            'scope_notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
