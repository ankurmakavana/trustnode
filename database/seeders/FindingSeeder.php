<?php

namespace Database\Seeders;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Seeder;

class FindingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'administrator');
        })->first();

        if (! $admin) {
            return;
        }

        $assets = Asset::all();
        $targets = Target::all();
        $scans = Scan::all();

        // Standard vulnerability templates using real VAPT vocabulary
        $findings = [
            [
                'title' => 'SQL Injection Vulnerability in Authentication Endpoint',
                'cve' => 'CVE-2024-34201',
                'cvss_score' => 9.8,
                'severity' => FindingSeverity::CRITICAL,
                'status' => FindingStatus::OPEN,
                'category' => 'web_application',
                'cwe' => 'CWE-89',
                'description' => 'A SQL Injection vulnerability was identified in the authentication controller where parameterized query input validation is bypassed.',
                'technical_details' => 'POST /api/auth/login parameter "username" is passed directly to the raw DB query interface without escaping.',
                'business_impact' => 'An attacker could completely compromise the database server and bypass authentication checks.',
                'remediation' => 'Migrate to standard Eloquent query generation or use bind parameters explicitly.',
                'evidence' => 'Parameter "username" value: admin\' OR \'1\'=\'1',
            ],
            [
                'title' => 'Reflected Cross-Site Scripting (XSS) in Dashboard Portal',
                'cve' => 'CVE-2024-10255',
                'cvss_score' => 7.5,
                'severity' => FindingSeverity::HIGH,
                'status' => FindingStatus::IN_PROGRESS,
                'category' => 'web_application',
                'cwe' => 'CWE-79',
                'description' => 'A cross-site scripting issue was identified because query strings parameters are rendered back to user dashboards without sanitization.',
                'technical_details' => 'URL query parameter "page_ref" is inserted into the main script block without escaping.',
                'business_impact' => 'Allows session hijacking, credentials theft or cookies manipulation in operators browser contexts.',
                'remediation' => 'Sanitize output using Blade template double curly brace escape notation.',
                'evidence' => 'http://trustnode.local/?page_ref=<script>alert(1)</script>',
            ],
            [
                'title' => 'Insecure Port Configurations on Kubernetes Host',
                'cve' => null,
                'cvss_score' => 5.3,
                'severity' => FindingSeverity::MEDIUM,
                'status' => FindingStatus::OPEN,
                'category' => 'network_ip',
                'cwe' => 'CWE-276',
                'description' => 'Unrestricted ingress port configurations were detected on network components exposing debug logs.',
                'technical_details' => 'Port 8081 is open and accessible from public IP addresses exposing stack traces.',
                'business_impact' => 'Information disclosure of backend internal paths.',
                'remediation' => 'Configure firewall policies to bind port 8081 to internal private VPC connections only.',
                'evidence' => 'nmap scan results: Port 8081 tcp open/http debug console',
            ],
        ];

        foreach ($findings as $f) {
            Finding::create(array_merge($f, [
                'asset_id' => $assets->random()?->id,
                'target_id' => $targets->random()?->id,
                'scan_id' => $scans->random()?->id,
                'assigned_analyst' => $admin->id,
                'created_by' => $admin->id,
            ]));
        }
    }
}
