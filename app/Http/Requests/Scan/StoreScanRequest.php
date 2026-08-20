<?php

namespace App\Http\Requests\Scan;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreScanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', new Enum(ScanType::class), function ($attribute, $value, $fail) {
                if (!in_array($value, [ScanType::REPOSITORY->value, ScanType::NETWORK_IP->value, ScanType::DATABASE->value])) {
                    $fail('Only Repository, Network IP, and Database scanning are supported in this release.');
                }
            }],
            'engine' => ['required', new Enum(ScanEngine::class)],
            'target' => ['required', 'string', 'max:255'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', new Enum(ScanStatus::class)],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
            'credentials.driver' => ['required_with:credentials', 'string'],
            'credentials.host' => ['required_with:credentials', 'string'],
            'credentials.port' => ['nullable', 'integer'],
            'credentials.database' => ['nullable', 'string'],
            'credentials.username' => ['required_with:credentials', 'string'],
            'credentials.password' => ['nullable', 'string'],
        ];
    }
}
