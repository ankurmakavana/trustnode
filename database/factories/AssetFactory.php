<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->word(),
            'type' => 'domain',
            'value' => $this->faker->url(),
            'description' => $this->faker->sentence(),
            'criticality' => 'medium',
            'status' => 'active',
            'risk_score' => 0.00,
            'owner' => $this->faker->name(),
            'notes' => $this->faker->sentence(),
            'organization_id' => \App\Models\Organization::factory(),
            'created_by' => function () {
                $user = \App\Models\User::first();
                if (!$user) {
                    $role = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->first();
                    $user = \App\Models\User::factory()->create(['role_id' => $role?->id]);
                }
                return $user->id;
            },
            'updated_by' => null,
        ];
    }
}
