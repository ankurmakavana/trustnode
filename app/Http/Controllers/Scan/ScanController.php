<?php

namespace App\Http\Controllers\Scan;

use App\DTO\Scan\ScanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scan\StoreScanRequest;
use App\Http\Requests\Scan\UpdateScanRequest;
use App\Http\Resources\Scan\ScanActivityLogResource;
use App\Http\Resources\Scan\ScanResource;
use App\Models\Scan;
use App\Services\Scan\ScanService;
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

        return new ScanResource($scan->load(['creator', 'updater']));
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

    public function downloadReport(Scan $scan)
    {
        $findings = \App\Models\Finding::where('scan_id', $scan->id)->get();
        $repository = \App\Models\Repository::where('name', $scan->target)->first();

        if (!$repository) {
            $repository = (object)[
                'name' => $scan->target,
                'visibility' => 'unknown',
                'default_branch' => 'main'
            ];
        }

        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0
        ];

        foreach ($findings as $finding) {
            $sev = strtolower($finding->severity->value ?? $finding->severity);
            if (array_key_exists($sev, $severityCounts)) {
                $severityCounts[$sev]++;
            } else {
                $severityCounts['info']++;
            }
        }

        return view('reports.repository_scan', [
            'scan' => $scan,
            'findings' => $findings,
            'repository' => $repository,
            'severityCounts' => $severityCounts
        ]);
    }
}
