<?php

namespace Database\Seeders;

use App\Models\Finding;
use App\Models\Risk;
use App\Models\RiskHistory;
use App\Models\RiskTreatment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiskSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (! $admin) {
            return;
        }

        $findings = Finding::limit(5)->get();

        // 1. Critical SQL Injection risk
        $risk1 = Risk::create([
            'uuid' => (string) Str::uuid(),
            'risk_id' => 'TN-RISK-000001',
            'title' => 'SQL Injection in User Authentication Service',
            'description' => 'Unauthenticated SQL Injection in login endpoint allows administrative bypass and database schema leakage.',
            'business_impact' => 'Catastrophic business impact including potential data privacy breach, regulatory audit failures (GDPR/PCI), and financial liability.',
            'technical_impact' => 'Complete compromise of active database storage nodes and extraction of administrative session cookies.',
            'likelihood' => 'Likely',
            'impact' => 'Catastrophic',
            'risk_score' => 20,
            'risk_level' => 'Critical',
            'status' => 'Mitigating',
            'owner_id' => $admin->id,
            'due_date' => now()->addDays(7),
            'review_date' => now()->addDays(30),
            'created_by' => $admin->id,
        ]);

        if ($findings->count() > 0) {
            $risk1->findings()->attach($findings->pluck('id')->first());
        }

        RiskHistory::create([
            'risk_id' => $risk1->id,
            'action' => 'Created',
            'description' => 'Identified SQL injection risk during VAPT scan triangulation.',
            'user_id' => $admin->id,
        ]);

        RiskTreatment::create([
            'risk_id' => $risk1->id,
            'treatment_type' => 'Mitigate',
            'description' => 'Implement parameterized database queries across authentication modules.',
            'target_date' => now()->addDays(5),
            'status' => 'In Progress',
            'created_by' => $admin->id,
        ]);

        // 2. High Port Exposure risk
        $risk2 = Risk::create([
            'uuid' => (string) Str::uuid(),
            'risk_id' => 'TN-RISK-000002',
            'title' => 'Insecure Port Configurations on Perimeter Firewalls',
            'description' => 'Open administration services exposed directly to public ingress interfaces.',
            'business_impact' => 'Moderate business impact; may allow attackers to execute brute-force attacks against portal components.',
            'technical_impact' => 'Exposed diagnostic and management shell ports (SSH, FTP) could yield service compromise.',
            'likelihood' => 'Possible',
            'impact' => 'Major',
            'risk_score' => 12,
            'risk_level' => 'High',
            'status' => 'Accepted',
            'owner_id' => $admin->id,
            'due_date' => now()->addDays(30),
            'review_date' => now()->addDays(90),
            'accepted' => true,
            'accepted_by' => $admin->id,
            'accepted_at' => now(),
            'created_by' => $admin->id,
        ]);

        if ($findings->count() > 1) {
            $risk2->findings()->attach($findings->pluck('id')->skip(1)->first());
        }

        RiskHistory::create([
            'risk_id' => $risk2->id,
            'action' => 'Created',
            'description' => 'Created ports risk record.',
            'user_id' => $admin->id,
        ]);

        RiskHistory::create([
            'risk_id' => $risk2->id,
            'action' => 'Accepted',
            'description' => 'Risk formal acceptance signed off due to legacy service dependencies.',
            'user_id' => $admin->id,
        ]);

        RiskTreatment::create([
            'risk_id' => $risk2->id,
            'treatment_type' => 'Accept',
            'description' => 'Legacy server operations require these port definitions; offset risk via strict ingress IP filtering.',
            'target_date' => now()->addDays(90),
            'status' => 'Completed',
            'created_by' => $admin->id,
        ]);
    }
}
