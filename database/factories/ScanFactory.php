<?php

namespace Database\Factories;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'repository_id' => null,
            'local_project_id' => null,
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => 'target',
            'organization_id' => \App\Models\Organization::factory(),
            'schedule' => null,
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'started_at' => null,
            'completed_at' => null,
            'duration' => null,
            'created_by' => function () {
                $user = \App\Models\User::first();
                if (!$user) {
                    $role = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->first();
                    $user = \App\Models\User::factory()->create(['role_id' => $role?->id]);
                }
                return $user->id;
            },
            'updated_by' => null,
            'import_job_id' => null,
        ];
    }
}
