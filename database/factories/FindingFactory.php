<?php

namespace Database\Factories;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        $cvss = $this->faker->randomFloat(1, 0, 10.0);
        $severity = $this->resolveSeverity($cvss);

        return [
            'uuid' => $this->faker->uuid(),
            'finding_id' => 'TN-FIND-'.str_pad($this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(4),
            'cve' => 'CVE-'.$this->faker->year().'-'.$this->faker->numberBetween(1000, 9999),
            'cvss_score' => $cvss,
            'severity' => $severity,
            'status' => $this->faker->randomElement(FindingStatus::cases()),
            'category' => $this->faker->randomElement(['web_application', 'network_ip', 'container_audit', 'cloud_infrastructure']),
            'cwe' => 'CWE-'.$this->faker->numberBetween(79, 94),
            'description' => $this->faker->paragraph(2),
            'technical_details' => $this->faker->paragraph(3),
            'business_impact' => $this->faker->paragraph(1),
            'remediation' => $this->faker->paragraph(2),
            'evidence' => $this->faker->paragraph(1),
            'asset_id' => null,
            'target_id' => null,
            'scan_id' => null,
            'assigned_analyst' => function () {
                return User::first()?->id ?? User::factory();
            },
            'created_by' => function () {
                return User::first()?->id ?? User::factory();
            },
        ];
    }

    private function resolveSeverity(float $cvss): FindingSeverity
    {
        if ($cvss >= 9.0) {
            return FindingSeverity::CRITICAL;
        }
        if ($cvss >= 7.0) {
            return FindingSeverity::HIGH;
        }
        if ($cvss >= 4.0) {
            return FindingSeverity::MEDIUM;
        }
        if ($cvss >= 0.1) {
            return FindingSeverity::LOW;
        }

        return FindingSeverity::INFO;
    }
}
