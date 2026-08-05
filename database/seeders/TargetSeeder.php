<?php

namespace Database\Seeders;

use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;
use App\Models\Target;
use App\Models\TargetTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        if (! $admin) {
            return;
        }

        // 1. Seed some target tags
        $tags = [
            'Production' => '#fee2e2',
            'Internal' => '#e0e7ff',
            'PCI-DSS' => '#fae8ff',
            'DMZ' => '#ffedd5',
            'Legacy' => '#ffedd5',
        ];

        $tagModels = [];
        foreach ($tags as $name => $color) {
            $tagModels[$name] = TargetTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => $color]
            );
        }

        // 2. Default target entries
        $targets = [
            [
                'name' => 'Main Gate VPN Gateway',
                'type' => TargetType::IP_ADDRESS,
                'value' => '10.10.1.254',
                'environment' => TargetEnvironment::INTERNAL,
                'criticality' => TargetCriticality::CRITICAL,
                'status' => TargetStatus::ACTIVE,
                'description' => 'Main IPsec and SSL-VPN gateway for staff remote access.',
                'scope_notes' => 'Do not scan during corporate updates (Friday 22:00 to Saturday 04:00 UTC).',
                'tags' => ['Internal', 'Production'],
            ],
            [
                'name' => 'Single Sign-On Authentication Portal',
                'type' => TargetType::URL,
                'value' => 'https://auth.internal/sso/login',
                'environment' => TargetEnvironment::PRODUCTION,
                'criticality' => TargetCriticality::CRITICAL,
                'status' => TargetStatus::ACTIVE,
                'description' => 'Central authentication platform serving all sub-apps.',
                'scope_notes' => 'Perform credential stuffing simulation only under explicit SOC supervision.',
                'tags' => ['Production', 'PCI-DSS'],
            ],
            [
                'name' => 'Main Public Corporate Site',
                'type' => TargetType::DOMAIN,
                'value' => 'corp.internal',
                'environment' => TargetEnvironment::PRODUCTION,
                'criticality' => TargetCriticality::MEDIUM,
                'status' => TargetStatus::ACTIVE,
                'description' => 'Static corporate informational site.',
                'scope_notes' => 'Allowed for automated vulnerability scanning at any time.',
                'tags' => ['Production', 'DMZ'],
            ],
            [
                'name' => 'Development Staging Environment API',
                'type' => TargetType::API_ENDPOINT,
                'value' => 'https://api.internal/staging/v1',
                'environment' => TargetEnvironment::STAGING,
                'criticality' => TargetCriticality::LOW,
                'status' => TargetStatus::UNDER_REVIEW,
                'description' => 'Staging sandbox for backend API features.',
                'scope_notes' => 'Do not alter database transaction records during active QA cycles.',
                'tags' => ['Legacy'],
            ],
        ];

        foreach ($targets as $data) {
            $target = Target::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'value' => $data['value'],
                'environment' => $data['environment'],
                'criticality' => $data['criticality'],
                'status' => $data['status'],
                'description' => $data['description'],
                'scope_notes' => $data['scope_notes'],
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $targetTagIds = [];
            foreach ($data['tags'] as $tagName) {
                if (isset($tagModels[$tagName])) {
                    $targetTagIds[] = $tagModels[$tagName]->id;
                }
            }
            $target->tags()->sync($targetTagIds);
        }
    }
}
