<?php

namespace App\Http\Requests\Asset;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use App\Rules\AssetValueRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handled by policies & middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AssetType::class)],
            'value' => [
                'required',
                'string',
                new AssetValueRule($this->input('type')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'criticality' => ['nullable', Rule::enum(AssetCriticality::class)],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'risk_score' => ['nullable', 'numeric', 'between:0.00,10.00'],
            'owner' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'asset_group_id' => ['nullable', 'integer', 'exists:asset_groups,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
