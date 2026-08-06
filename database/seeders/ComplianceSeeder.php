<?php

namespace Database\Seeders;

use App\Models\ComplianceControl;
use App\Models\ComplianceFramework;
use App\Models\Finding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplianceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $frameworks = [
            [
                'name' => 'OWASP Top 10 2021',
                'code' => 'OWASP',
                'description' => 'Standard awareness document for developers and web application security.',
                'controls' => [
                    ['code' => 'A01', 'title' => 'Broken Access Control', 'description' => 'Bypassing access control checks by modifying parameters.'],
                    ['code' => 'A02', 'title' => 'Cryptographic Failures', 'description' => 'Sensitive data exposure due to weak encryption algorithms.'],
                    ['code' => 'A03', 'title' => 'Injection', 'description' => 'SQL injection, command injection, XSS, LDAP injection vulnerabilities.'],
                    ['code' => 'A04', 'title' => 'Insecure Design', 'description' => 'Flaws in application architectural patterns and threat models.'],
                    ['code' => 'A05', 'title' => 'Security Misconfiguration', 'description' => 'Exposed interfaces, default passwords, default configurations.'],
                    ['code' => 'A06', 'title' => 'Vulnerable and Outdated Components', 'description' => 'Using unpatched third-party library versions.'],
                    ['code' => 'A07', 'title' => 'Identification and Authentication Failures', 'description' => 'Brute force exposure, session hijacking parameters.'],
                    ['code' => 'A08', 'title' => 'Software and Data Integrity Failures', 'description' => 'Insecure deserialization, untrusted CDNs.'],
                    ['code' => 'A09', 'title' => 'Security Logging and Monitoring Failures', 'description' => 'Absence of audit logging trails during security scans.'],
                    ['code' => 'A10', 'title' => 'Server-Side Request Forgery (SSRF)', 'description' => 'Abusing server credentials to make internal requests.'],
                ],
            ],
            [
                'name' => 'MITRE ATT&CK Matrix',
                'code' => 'MITRE',
                'description' => 'Globally-accessible knowledge base of adversary tactics and techniques.',
                'controls' => [
                    ['code' => 'TA0001', 'title' => 'Initial Access', 'description' => 'Adversaries attempting to get into your network environment.'],
                    ['code' => 'TA0002', 'title' => 'Execution', 'description' => 'Adversaries running malicious commands or remote code.'],
                    ['code' => 'TA0003', 'title' => 'Persistence', 'description' => 'Adversaries maintaining access across reboots/restarts.'],
                    ['code' => 'TA0004', 'title' => 'Privilege Escalation', 'description' => 'Adversaries trying to gain higher-level administrative permissions.'],
                    ['code' => 'TA0005', 'title' => 'Defense Evasion', 'description' => 'Adversaries trying to avoid detection during scans.'],
                    ['code' => 'TA0006', 'title' => 'Credential Access', 'description' => 'Adversaries stealing usernames and password values.'],
                    ['code' => 'TA0007', 'title' => 'Discovery', 'description' => 'Adversaries figuring out the active network layout.'],
                    ['code' => 'TA0008', 'title' => 'Lateral Movement', 'description' => 'Adversaries moving through host target subnets.'],
                ],
            ],
            [
                'name' => 'ISO/IEC 27001:2022 Annex A',
                'code' => 'ISO',
                'description' => 'Annex A control mappings for security controls reference.',
                'controls' => [
                    ['code' => 'A.5', 'title' => 'Organizational Controls', 'description' => 'Policies for information security.'],
                    ['code' => 'A.6', 'title' => 'People Controls', 'description' => 'Vetting, screening, and administrative controls.'],
                    ['code' => 'A.7', 'title' => 'Physical Controls', 'description' => 'Perimeter guards, locks, and monitoring tools.'],
                    ['code' => 'A.8', 'title' => 'Technological Controls', 'description' => 'Vulnerability scanning, networks filtering, endpoints patching.'],
                ],
            ],
            [
                'name' => 'PCI DSS v4.0',
                'code' => 'PCI',
                'description' => 'Payment Card Industry Data Security Standard requirements.',
                'controls' => [
                    ['code' => 'Req 1', 'title' => 'Network Security Controls', 'description' => 'Enforce firewall and routing boundary rules.'],
                    ['code' => 'Req 2', 'title' => 'Secure Configurations', 'description' => 'Apply system hardening profiles and remove defaults.'],
                    ['code' => 'Req 6', 'title' => 'Bespoke Software Protection', 'description' => 'Address software vulnerabilities and patch releases.'],
                    ['code' => 'Req 11', 'title' => 'Regular System Testing', 'description' => 'Execute vulnerability assessment scans monthly.'],
                ],
            ],
            [
                'name' => 'NIST CSF v2.0',
                'code' => 'NIST',
                'description' => 'National Institute of Standards and Technology Cybersecurity Framework.',
                'controls' => [
                    ['code' => 'GV', 'title' => 'Govern', 'description' => 'Establish cybersecurity risk management strategies.'],
                    ['code' => 'ID', 'title' => 'Identify', 'description' => 'Map assets, targets, scopes, and vulnerabilities.'],
                    ['code' => 'PR', 'title' => 'Protect', 'description' => 'Deploy firewall filtering and endpoint protection controls.'],
                    ['code' => 'DE', 'title' => 'Detect', 'description' => 'Execute scanning and threat detection engines.'],
                    ['code' => 'RS', 'title' => 'Respond', 'description' => 'Coordinate response actions during incident events.'],
                    ['code' => 'RC', 'title' => 'Recover', 'description' => 'Restore operations from secure system backups.'],
                ],
            ],
            [
                'name' => 'SOC 2 Trust Services Criteria',
                'code' => 'SOC2',
                'description' => 'System and Organization Controls for security, availability, processing integrity.',
                'controls' => [
                    ['code' => 'CC6', 'title' => 'Logical Access Controls', 'description' => 'Enforce user identity validation protocols.'],
                    ['code' => 'CC7', 'title' => 'System Operations & Vulnerability Management', 'description' => 'Assess network vulnerability threat scores.'],
                ],
            ],
        ];

        $findings = Finding::all();

        foreach ($frameworks as $fwData) {
            DB::transaction(function () use ($fwData, $findings) {
                $framework = ComplianceFramework::create([
                    'name' => $fwData['name'],
                    'code' => $fwData['code'],
                    'description' => $fwData['description'],
                ]);

                foreach ($fwData['controls'] as $ctrlData) {
                    $control = ComplianceControl::create([
                        'framework_id' => $framework->id,
                        'code' => $ctrlData['code'],
                        'title' => $ctrlData['title'],
                        'description' => $ctrlData['description'],
                    ]);

                    // Map matching findings if there is a keyword match
                    foreach ($findings as $finding) {
                        $titleLower = strtolower($finding->title);
                        $codeLower = strtolower($control->code);
                        $titleFwLower = strtolower($control->title);

                        $shouldLink = false;
                        if ($framework->code === 'OWASP' && $control->code === 'A03' && (str_contains($titleLower, 'injection') || str_contains($titleLower, 'xss'))) {
                            $shouldLink = true;
                        } elseif ($framework->code === 'OWASP' && $control->code === 'A05' && (str_contains($titleLower, 'port') || str_contains($titleLower, 'exposure') || str_contains($titleLower, 'configuration'))) {
                            $shouldLink = true;
                        } elseif ($framework->code === 'OWASP' && $control->code === 'A01' && (str_contains($titleLower, 'access') || str_contains($titleLower, 'bypass') || str_contains($titleLower, 'unauthorized'))) {
                            $shouldLink = true;
                        } elseif ($framework->code === 'MITRE' && $control->code === 'TA0001' && str_contains($titleLower, 'exposure')) {
                            $shouldLink = true;
                        } elseif ($framework->code === 'ISO' && $control->code === 'A.8' && str_contains($titleLower, 'injection')) {
                            $shouldLink = true;
                        } elseif ($framework->code === 'PCI' && $control->code === 'Req 11') {
                            $shouldLink = true;
                        }

                        if ($shouldLink) {
                            $control->findings()->attach($finding->id, [
                                'status' => 'Failed',
                                'notes' => 'Active scan finding maps to a failed compliance control parameter.',
                            ]);
                        }
                    }
                }
            });
        }
    }
}
