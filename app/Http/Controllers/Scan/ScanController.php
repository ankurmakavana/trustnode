<?php

namespace App\Http\Controllers\Scan;

use App\DTO\Scan\ScanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scan\StoreScanRequest;
use App\Http\Requests\Scan\UpdateScanRequest;
use App\Http\Resources\Scan\ScanActivityLogResource;
use App\Http\Resources\Scan\ScanResource;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Jobs\GenerateScanReport;
use App\Services\Scan\ScanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScanController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ScanService $scanService) {}

    /**
     * Display a listing of scans matching search filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Scan::class);

        $filters = $request->only(['search', 'type', 'engine', 'status', 'sort_by', 'sort_order']);
        $scans = $this->scanService->list($filters, (int) $request->input('per_page', 15));

        return ScanResource::collection($scans);
    }

    /**
     * Store a newly configured scan.
     */
    public function store(StoreScanRequest $request): JsonResponse
    {
        $this->authorize('execute', Scan::class);

        $dto = ScanData::fromArray($request->validated());
        $scan = $this->scanService->create($dto, $request->user());

        if ($scan->type === \App\Enums\Scan\ScanType::NETWORK_IP) {
            \App\Jobs\ScanInfrastructureJob::dispatch($scan);
        } elseif ($scan->type === \App\Enums\Scan\ScanType::DATABASE) {
            $creds = $request->input('credentials', []);
            $vault = app(\App\Services\Scan\Database\DatabaseCredentialVault::class);
            $token = $vault->store($creds);
            
            \App\Jobs\ScanDatabaseJob::dispatch($scan, $token);
        }

        return (new ScanResource($scan->load(['creator'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified scan run metadata.
     */
    public function show(Scan $scan): ScanResource
    {
        $this->authorize('viewAny', Scan::class);

        return new ScanResource($scan->load(['creator', 'updater', 'repository']));
    }

    /**
     * Update the parameters of a scan profile.
     */
    public function update(UpdateScanRequest $request, Scan $scan): ScanResource
    {
        $this->authorize('execute', Scan::class);

        $dto = ScanData::fromArray($request->validated());
        $updatedScan = $this->scanService->update($scan, $dto, $request->user());

        return new ScanResource($updatedScan->load(['creator', 'updater']));
    }

    /**
     * Remove the specified scan configuration.
     */
    public function destroy(Request $request, Scan $scan): JsonResponse
    {
        $this->authorize('execute', Scan::class);

        $this->scanService->delete($scan, $request->user());

        return response()->json(['message' => 'Scan configuration deleted successfully.']);
    }

    /**
     * Display activity log history for specific scan.
     */
    public function activityLogs(Scan $scan): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Scan::class);

        $logs = $scan->activityLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return ScanActivityLogResource::collection($logs);
    }

    public function generateReport(Request $request, Scan $scan): JsonResponse
    {
        $this->authorize('viewAny', Scan::class);

        $existing = ScanReport::where('scan_id', $scan->id)
            ->whereIn('status', ['queued', 'generating'])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Report is already generating.',
                'report_id' => $existing->id,
                'status' => $existing->status
            ], 202);
        }

        $report = ScanReport::create([
            'scan_id' => $scan->id,
            'requested_by' => $request->user()->id,
            'status' => 'queued',
        ]);

        GenerateScanReport::dispatch($report);

        return response()->json([
            'message' => 'Report generation queued.',
            'report_id' => $report->id,
            'status' => 'queued'
        ], 202);
    }

    public function reportStatus(Scan $scan): JsonResponse
    {
        $this->authorize('viewAny', Scan::class);

        $report = ScanReport::where('scan_id', $scan->id)->latest()->first();

        if (! $report) {
            return response()->json(['message' => 'No report found.'], 404);
        }

        return response()->json($report);
    }

    public function downloadReport(Scan $scan)
    {
        $this->authorize('viewAny', Scan::class);

        $report = ScanReport::where('scan_id', $scan->id)->where('status', 'completed')->latest()->first();

        if (! $report || ! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['message' => 'Report not available or still generating.'], 404);
        }

        $date = $scan->created_at ? $scan->created_at->format('Y-m-d') : date('Y-m-d');
        $safeRepoName = str_replace(['/', '\\', '_'], '-', $scan->target ?? 'repo');
        $filename = "trustnode-{$safeRepoName}-scan-{$date}.pdf";

        return Storage::disk('local')->download($report->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
    public function storeLocal(Request $request): JsonResponse
    {
        $this->authorize('execute', Scan::class);

        $request->validate([
            'archive' => 'required|file|mimes:zip|max:102400', // max 100MB
            'target' => 'required|string',
        ]);

        $targetDir = $request->input('target');

        // 1. Create a LocalProject record for stable identity
        $localProject = \App\Models\LocalProject::firstOrCreate(
            ['path' => $targetDir],
            [
                'name' => basename($targetDir),
                'created_by' => $request->user()->id,
            ]
        );

        // 2. Create a Scan record
        $scan = Scan::create([
            'repository_id' => null,
            'local_project_id' => $localProject->id,
            'name'          => 'Local Scan: ' . basename($targetDir),
            'target'        => $targetDir,
            'type'          => 'local',
            'engine'        => 'localscanner',
            'status'        => \App\Enums\Scan\ScanStatus::QUEUED,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'created_by' => $request->user()->id,
        ]);

        // 2. Store the uploaded archive
        $path = $request->file('archive')->storeAs('local-scans', "scan-{$scan->id}.zip", 'local');

        // 3. Dispatch the job
        // We will create a ScanLocalJob or reuse ScanRepositoryJob.
        // Reusing ScanRepositoryJob requires a Repository model. Since this is local, we probably need a ScanLocalJob.
        \App\Jobs\ScanLocalJob::dispatch($scan, $path);

        return response()->json($scan, 201);
    }
}
