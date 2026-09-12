<?php

namespace Database\Factories;

use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->word(),
            'code' => $this->faker->randomElement(['nmap', 'greenbone', 'nessus', 'burp', 'github', 'gitlab']),
            'type' => $this->faker->randomElement(['scanner', 'collaboration', 'vcs', 'ticketing']),
            'organization_id' => \App\Models\Organization::factory(),
            'environment' => 'production',
            'description' => $this->faker->sentence(),
            'status' => 'Disconnected',
            'host' => null,
            'port' => null,
            'username' => null,
            'tls' => false,
            'health_status' => 'Unreachable',
            'last_check_at' => null,
            'options' => null,
            'tags' => null,
        ];
    }
}
