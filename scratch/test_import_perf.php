<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImportJob;
use App\Models\ImportFile;
use App\Services\Import\ImportService;
use App\Services\Import\ValidationService;
use App\Services\Import\NormalizationService;
use App\Services\Import\AssetMapper;
use App\Services\Import\FindingMapper;
use App\Services\Import\FingerprintService;
use App\Services\Import\ComplianceMapper;
use App\Services\Import\RiskMapper;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

// Reset database tables to get clean timings if wanted, or just run
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::table('findings')->truncate();
DB::table('assets')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "=== TRUSTNODE IMPORT PIPELINE PERFORMANCE DIAGNOSTIC ===\n";

$xml = '<nmaprun><host><address addr="192.168.10.5" addrtype="ipv4"/><ports>';
for ($i = 1; $i <= 500; $i++) {
    $xml .= '<port portid="' . $i . '"><state state="open"/><service name="svc' . $i . '"/></port>';
}
$xml .= '</ports></host></nmaprun>';

echo "XML size: " . strlen($xml) . " bytes\n";

// Stage 1: Upload (File Save & Db Creation)
$t1 = microtime(true);
$job = ImportJob::create([
    'uuid' => (string) Str::uuid(),
    'status' => 'pending',
    'progress' => 0,
    'source_type' => 'file',
    'created_by' => 1,
]);
$filepath = 'imports/perf_test_' . time() . '.xml';
Storage::put($filepath, $xml);
ImportFile::create([
    'import_job_id' => $job->id,
    'filename' => 'perf_test.xml',
    'filepath' => $filepath,
    'filesize' => strlen($xml),
    'mime_type' => 'text/xml',
]);
$t2 = microtime(true);
echo "Upload/Save Duration: " . round(($t2 - $t1) * 1000, 2) . " ms\n";

// Stage 2: Validate
$validator = new ValidationService();
$res = $validator->validate($xml, 'nmap');
$t3 = microtime(true);
echo "Validation Duration: " . round(($t3 - $t2) * 1000, 2) . " ms (Valid: " . ($res['valid'] ? 'yes' : 'no') . ")\n";

// Stage 3: Parse
$importService = $app->make(ImportService::class);
$adapter = $importService->getAdapter('nmap');
$parsed = $adapter->parse($xml);
$t4 = microtime(true);
echo "Parsing Duration: " . round(($t4 - $t3) * 1000, 2) . " ms\n";
echo " - Extracted Assets: " . count($parsed['assets']) . "\n";
echo " - Extracted Findings: " . count($parsed['findings']) . "\n";

// Stage 4: Normalize
$normalizer = new NormalizationService();
$normalizedAssets = [];
foreach ($parsed['assets'] as $asset) {
    $normalizedAssets[] = [
        'name' => $asset['name'] ?? $asset['value'],
        'type' => $asset['type'] ?? 'Host',
        'value' => $asset['value'],
        'description' => $asset['description'] ?? null,
    ];
}
$normalizedFindings = [];
foreach ($parsed['findings'] as $finding) {
    $normalizedFindings[] = [
        'title' => $finding['title'],
        'severity' => $normalizer->normalizeSeverity($finding['severity'] ?? 'low'),
        'category' => $finding['category'] ?? 'Host',
        'cve' => $finding['cve'] ?? null,
        'cvss_score' => $normalizer->normalizeCvss($finding['cvss_score'] ?? null),
        'cwe' => $finding['cwe'] ?? null,
        'description' => $finding['description'] ?? null,
        'remediation' => $finding['remediation'] ?? null,
        'technical_details' => $finding['technical_details'] ?? null,
        'asset_value' => $finding['asset_value'],
    ];
}
$t5 = microtime(true);
echo "Normalization Duration: " . round(($t5 - $t4) * 1000, 2) . " ms\n";

// Stage 5: Asset Creation
$assetMapper = $app->make(AssetMapper::class);
$resolvedAssets = [];
foreach ($normalizedAssets as $rawAsset) {
    $asset = $assetMapper->map($rawAsset, $job);
    $resolvedAssets[$rawAsset['value']] = $asset;
}
$t6 = microtime(true);
echo "Asset Creation Duration: " . round(($t6 - $t5) * 1000, 2) . " ms\n";

// Stage 6: Finding Creation & Deduplication
$findingMapper = $app->make(FindingMapper::class);
$createdFindings = [];
$dupCount = 0;
$saveCount = 0;

$t7_start = microtime(true);
DB::beginTransaction();
try {
    foreach ($normalizedFindings as $rawFinding) {
        $asset = $resolvedAssets[$rawFinding['asset_value']] ?? null;
        if (!$asset) continue;

        $isDup = \App\Models\Finding::where('title', $rawFinding['title'])
            ->where('asset_id', $asset->id)
            ->exists();

        $finding = $findingMapper->map($rawFinding, $asset, $job);
        if ($finding) {
            $createdFindings[] = $finding;
            if ($isDup) {
                $dupCount++;
            } else {
                $saveCount++;
            }
        }
    }
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    echo "ERROR in finding creation: " . $e->getMessage() . "\n";
}
$t7 = microtime(true);
echo "Finding Creation + Dup Check Duration: " . round(($t7 - $t7_start) * 1000, 2) . " ms\n";
echo " - Saved Findings: " . $saveCount . "\n";
echo " - Duplicates: " . $dupCount . "\n";

// Stage 7: Compliance Mapping
$complianceMapper = $app->make(ComplianceMapper::class);
foreach ($createdFindings as $finding) {
    $complianceMapper->map($finding);
}
$t8 = microtime(true);
echo "Compliance Mapping Duration: " . round(($t8 - $t7) * 1000, 2) . " ms\n";

// Stage 8: Risk Recalculation
$riskMapper = $app->make(RiskMapper::class);
$processedAssetIds = [];
foreach ($createdFindings as $finding) {
    if ($finding->asset && !in_array($finding->asset_id, $processedAssetIds)) {
        $riskMapper->recalculateAssetRisk($finding->asset);
        $processedAssetIds[] = $finding->asset_id;
    }
}
$t9 = microtime(true);
echo "Risk Recalculation Duration: " . round(($t9 - $t8) * 1000, 2) . " ms\n";

echo "Total Import Duration: " . round(($t9 - $t1) * 1000, 2) . " ms\n";

// Clean up test data
$job->delete();
Storage::delete($filepath);
