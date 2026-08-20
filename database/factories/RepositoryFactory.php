<?php

namespace Database\Factories;

use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RepositoryFactory extends Factory
{
    protected $model = Repository::class;

    public function definition(): array
    {
        $owner = $this->faker->userName();
        $repo = $this->faker->slug(2);

        return [
            'uuid' => (string) Str::uuid(),
            'provider' => 'github',
            'repository_url' => "https://github.com/{$owner}/{$repo}",
            'repository_id' => null,
            'name' => "{$owner}/{$repo}",
            'visibility' => $this->faker->randomElement(['public', 'private']),
            'default_branch' => $this->faker->randomElement(['main', 'master']),
            'integration_credential_id' => null,
            'status' => 'Connected',
            'last_scan_at' => null,
            'created_by' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
