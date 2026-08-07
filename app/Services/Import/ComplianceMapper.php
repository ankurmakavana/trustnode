<?php

namespace App\Services\Import;

use App\Models\ComplianceControl;
use App\Models\Finding;
use Illuminate\Support\Facades\DB;

class ComplianceMapper
{
    /**
     * Map finding to compliance control if applicable.
     */
    public function map(Finding $finding): void
    {
        // Simple heuristic: map Network finding to CIS controls, Web findings to OWASP
        $category = strtolower($finding->category);
        $control = null;

        if (str_contains($category, 'web')) {
            $control = ComplianceControl::where('code', 'like', 'A%')->first();
        } else {
            $control = ComplianceControl::first();
        }

        if ($control) {
            // Check if relationship already exists
            $exists = DB::table('finding_compliance')
                ->where('finding_id', $finding->id)
                ->where('control_id', $control->id)
                ->exists();

            if (! $exists) {
                DB::table('finding_compliance')->insert([
                    'finding_id' => $finding->id,
                    'control_id' => $control->id,
                    'status' => 'Failed',
                    'notes' => 'Vulnerability finding imported via automated scan.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
