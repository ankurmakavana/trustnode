<?php

namespace Database\Factories;

use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Enums\CodebaseProject\ScanProfile;
use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CodebaseProjectFactory extends Factory
{
    protected $model = CodebaseProject::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->words(2, true) . ' Project',
            'path' => $this->faker->randomElement([
                'my-project',
                'projects/backend',
                'client-a/api',
                'services/auth',
                'microservices/gateway',
            ]),
            'scan_profile' => ScanProfile::STANDARD,
            'continuous_scan_enabled' => false,
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'status' => CodebaseProjectStatus::ACTIVE,
        ];
    }

    /**
     * Configure the project with continuous scanning enabled.
     */
    public function continuousScan(): static
    {
        return $this->state(fn(array $attributes) => [
            'continuous_scan_enabled' => true,
        ]);
    }

    /**
     * Configure the project with full scan profile.
     */
    public function fullScan(): static
    {
        return $this->state(fn(array $attributes) => [
            'scan_profile' => ScanProfile::FULL,
        ]);
    }

    /**
     * Configure the project as archived.
     */
    public function archived(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => CodebaseProjectStatus::ARCHIVED,
        ]);
    }
}
