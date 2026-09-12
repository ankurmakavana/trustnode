<?php

namespace Database\Factories;

use App\Models\Target;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TargetFactory extends Factory
{
    protected $model = Target::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->domainName(),
            'type' => 'domain',
            'value' => $this->faker->url(),
            'environment' => 'production',
            'description' => $this->faker->sentence(),
            'criticality' => 'medium',
            'status' => 'active',
            'scope_notes' => $this->faker->sentence(),
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
