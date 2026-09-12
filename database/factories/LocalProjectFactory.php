<?php

namespace Database\Factories;

use App\Models\LocalProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocalProjectFactory extends Factory
{
    protected $model = LocalProject::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->word(),
            'path' => $this->faker->filePath(),
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
