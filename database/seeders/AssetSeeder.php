<?php

namespace Database\Seeders;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssetSeeder extends Seeder
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

        // 1. Create default Groups
        $groups = [
            'Internal Workspace' => 'Assets inside the internal private network scope.',
            'DMZ Public Services' => 'Public-facing corporate endpoints.',
            'Staging Environments' => 'Pre-release staging environments.',
        ];

        $groupModels = [];
        foreach ($groups as $name => $desc) {
            $groupModels[$name] = AssetGroup::firstOrCreate(
                ['name' => $name],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => $desc,
                    'created_by' => $admin->id,
                ]
            );
        }

        // 2. Create default Tags
        $tags = [
            'Production' => '#fee2e2',
            'Internal' => '#e0e7ff',
            'PCI-DSS' => '#fae8ff',
            'Legacy' => '#ffedd5',
            'Docker' => '#dcfce7',
        ];

        $tagModels = [];
        foreach ($tags as $name => $color) {
            $tagModels[$name] = AssetTag::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'color' => $color,
                ]
            );
        }

        // 3. Create realistic cybersecurity assets
        $assets = [
            [
                'name' => 'Internal Authentication Server',
                'type' => AssetType::SUBDOMAIN,
                'value' => 'auth.internal',
                'description' => 'Single sign-on portal for internal employee access.',
                'criticality' => AssetCriticality::CRITICAL,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 9.20,
                'owner' => 'Security Operations Center',
                'group' => 'Internal Workspace',
                'tags' => ['Production', 'Internal', 'PCI-DSS'],
            ],
            [
                'name' => 'Main Corporate Gateway Router',
                'type' => AssetType::IPV4,
                'value' => '10.10.1.1',
                'description' => 'Main firewall and edge router gateway.',
                'criticality' => AssetCriticality::CRITICAL,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 8.50,
                'owner' => 'Network Operations Center',
                'group' => 'Internal Workspace',
                'tags' => ['Production', 'Internal'],
            ],
            [
                'name' => 'Corporate Public Portal',
                'type' => AssetType::DOMAIN,
                'value' => 'corp.internal',
                'description' => 'Public-facing corporate blog and informational site.',
                'criticality' => AssetCriticality::MEDIUM,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 3.40,
                'owner' => 'Marketing IT',
                'group' => 'DMZ Public Services',
                'tags' => ['Production'],
            ],
            [
                'name' => 'Partner API Endpoint Server',
                'type' => AssetType::API_ENDPOINT,
                'value' => 'https://api.internal/v1/users',
                'description' => 'REST API backing partner authentication flow.',
                'criticality' => AssetCriticality::HIGH,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 7.80,
                'owner' => 'Platform API Team',
                'group' => 'DMZ Public Services',
                'tags' => ['Production', 'PCI-DSS'],
            ],
            [
                'name' => 'Legacy Customer Database Storage',
                'type' => AssetType::HOSTNAME,
                'value' => 'storage.internal',
                'description' => 'Local database backups holding client transaction archives.',
                'criticality' => AssetCriticality::CRITICAL,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 9.80,
                'owner' => 'Database Administrators',
                'group' => 'Internal Workspace',
                'tags' => ['Legacy', 'PCI-DSS', 'Internal'],
            ],
            [
                'name' => 'External VPN Endpoint Gateway',
                'type' => AssetType::SUBDOMAIN,
                'value' => 'vpn.internal',
                'description' => 'IPsec and OpenVPN entry point for external staff.',
                'criticality' => AssetCriticality::CRITICAL,
                'status' => AssetStatus::ACTIVE,
                'risk_score' => 8.90,
                'owner' => 'Infrastructure Security Team',
                'group' => 'Internal Workspace',
                'tags' => ['Production', 'Internal'],
            ],
            [
                'name' => 'Staging Portal Server',
                'type' => AssetType::URL,
                'value' => 'https://portal.internal',
                'description' => 'Testing and sandbox deployment web application.',
                'criticality' => AssetCriticality::LOW,
                'status' => AssetStatus::INACTIVE,
                'risk_score' => 1.20,
                'owner' => 'QA Team',
                'group' => 'Staging Environments',
                'tags' => ['Docker'],
            ],
        ];

        foreach ($assets as $a) {
            $group = $groupModels[$a['group']];
            $assetModel = Asset::firstOrCreate(
                ['value' => $a['value']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $a['name'],
                    'type' => $a['type'],
                    'description' => $a['description'],
                    'criticality' => $a['criticality'],
                    'status' => $a['status'],
                    'risk_score' => $a['risk_score'],
                    'owner' => $a['owner'],
                    'asset_group_id' => $group->id,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );

            // Sync tags
            $tagIds = [];
            foreach ($a['tags'] as $tagName) {
                if (isset($tagModels[$tagName])) {
                    $tagIds[] = $tagModels[$tagName]->id;
                }
            }
            $assetModel->tags()->sync($tagIds);
        }
    }
}
