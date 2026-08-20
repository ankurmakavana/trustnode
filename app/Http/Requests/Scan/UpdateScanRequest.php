<?php

namespace App\Http\Requests\Scan;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateScanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', new Enum(ScanType::class), function ($attribute, $value, $fail) {
                if (!in_array($value, [ScanType::REPOSITORY->value, ScanType::NETWORK_IP->value])) {
                    $fail('Only Repository and Network IP scanning are supported in this release.');
                }
            }],
            'engine' => ['sometimes', 'required', new Enum(ScanEngine::class)],
            'target' => ['sometimes', 'required', 'string', 'max:255'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', new Enum(ScanStatus::class)],
            'progress' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
