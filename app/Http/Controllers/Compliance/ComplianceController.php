<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\ComplianceFramework;
use App\Models\Finding;
use Illuminate\Http\JsonResponse;

class ComplianceController extends Controller
{
    /**
     * Get aggregate metrics dashboard.
     */
    public function stats(): JsonResponse
    {
        $frameworks = ComplianceFramework::with('controls.findings')->get();

        $stats = $frameworks->map(function ($fw) {
            $totalControls = $fw->controls->count();
            $passed = 0;
            $failed = 0;
            $notAssessed = 0;

            foreach ($fw->controls as $ctrl) {
                $findingsCount = $ctrl->findings->count();
                if ($findingsCount === 0) {
                    $passed++; // No open findings means passed/compliant control!
                } else {
                    $failed++; // Any active finding maps to a failed check
                }
            }

            // Standard safety
            if ($totalControls === 0) {
                $coverage = 100.00;
            } else {
                $coverage = round(($passed / $totalControls) * 100, 2);
            }

            return [
                'name' => $fw->name,
                'code' => $fw->code,
                'description' => $fw->description,
                'controls_count' => $totalControls,
                'passed' => $passed,
                'failed' => $failed,
                'not_assessed' => $notAssessed,
                'coverage' => $coverage,
            ];
        });

        // Compute overall workspace compliance score (average of all framework coverage scores)
        $avgScore = $stats->avg('coverage') ?? 100.00;

        return response()->json([
            'overall_compliance' => round($avgScore, 2),
            'frameworks' => $stats,
        ]);
    }

    /**
     * List all frameworks.
     */
    public function index(): JsonResponse
    {
        return $this->stats();
    }

    /**
     * Show framework detail profile with controls list and mapped findings.
     */
    public function show(string $code): JsonResponse
    {
        $framework = ComplianceFramework::where('code', $code)
            ->with(['controls.findings.asset', 'controls.findings.target'])
            ->firstOrFail();

        $controlsData = $framework->controls->map(function ($ctrl) {
            $status = $ctrl->findings->count() === 0 ? 'Passed' : 'Failed';

            return [
                'id' => $ctrl->id,
                'code' => $ctrl->code,
                'title' => $ctrl->title,
                'description' => $ctrl->description,
                'status' => $status,
                'findings' => $ctrl->findings->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'finding_id' => $f->finding_id,
                        'title' => $f->title,
                        'severity' => $f->severity,
                        'status' => $f->status,
                        'cvss_score' => $f->cvss_score,
                        'remediation' => $f->remediation,
                        'business_impact' => $f->business_impact,
                        'evidence' => $f->evidence,
                        'asset' => $f->asset ? [
                            'id' => $f->asset->id,
                            'name' => $f->asset->name,
                            'type' => $f->asset->type,
                        ] : null,
                        'target' => $f->target ? [
                            'id' => $f->target->id,
                            'target_id' => $f->target->target_id,
                            'destination' => $f->target->destination,
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'framework' => [
                'name' => $framework->name,
                'code' => $framework->code,
                'description' => $framework->description,
            ],
            'controls' => $controlsData,
        ]);
    }
}
