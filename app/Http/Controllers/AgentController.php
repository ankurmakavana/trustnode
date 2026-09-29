<?php

namespace App\Http\Controllers;

use App\Services\AgentService;
use App\Models\Finding;
use App\Models\FindingActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AgentController extends Controller
{
    /**
     * Get the health status of the local Agent runtime.
     */
    public function health(AgentService $agentService): JsonResponse
    {
        return response()->json(
            $agentService->getHealthStatus()
        );
    }

    /**
     * Get operational data for the Agent Console UI.
     */
    public function console(AgentService $agentService): JsonResponse
    {
        $health = $agentService->getHealthStatus();
        $agentId = $health['agent_id'];
        $heartbeatTimeout = config('agent.heartbeat.timeout', 60);

        // Calculate heartbeat age in seconds
        $lastHeartbeatAt = $health['last_heartbeat_at'];
        $heartbeatAgeSeconds = $lastHeartbeatAt ? max(0, now()->diffInSeconds(\Carbon\Carbon::parse($lastHeartbeatAt))) : null;
        $processStatus = ($health['status'] === 'running' && $health['heartbeat_stale']) ? 'stale' : $health['status'];

        // Worker & Current Active Task Telemetry
        $activeTask = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('status', 'processing')
            ->orderBy('updated_at', 'desc')
            ->first();

        $lastTask = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->orderBy('updated_at', 'desc')
            ->first();

        $workerStatus = 'unknown';
        if ($health['status'] === 'stopped') {
            $workerStatus = 'stopped';
        } elseif ($activeTask) {
            $workerStatus = 'processing';
        }

        $workerTelemetry = [
            'status' => $workerStatus,
            'current_task_id' => $activeTask?->id,
            'current_task_type' => $activeTask?->type,
            'current_target' => $activeTask ? (json_decode($activeTask->payload, true)['target'] ?? null) : null,
            'started_at' => $activeTask?->created_at,
            'duration_seconds' => $activeTask ? max(0, now()->diffInSeconds(\Carbon\Carbon::parse($activeTask->created_at))) : null,
        ];

        // Observation Telemetry
        $lastObservationTask = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('type', 'agent.observe_env')
            ->orderBy('updated_at', 'desc')
            ->first();

        $obsTarget = $lastObservationTask ? (json_decode($lastObservationTask->payload, true)['target'] ?? null) : null;
        $targetFile = null;
        if ($obsTarget !== null) {
            $isAbsolute = str_starts_with($obsTarget, '/') || str_starts_with($obsTarget, '\\') || preg_match('/^[a-zA-Z]:(\\\|\/)/', $obsTarget);
            $targetFile = $isAbsolute ? (string)$obsTarget : base_path((string)$obsTarget);
        }
        $targetExists = $targetFile ? file_exists($targetFile) : false;
        $targetLastChanged = $targetExists ? date('c', filemtime($targetFile)) : null;
        $targetCacheKey = $obsTarget ? 'agent_observe_env_hash_' . md5($targetFile) : null;
        $lastObservedHash = $targetCacheKey ? Cache::get($targetCacheKey) : null;

        $lastCompletedObsTask = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('type', 'agent.observe_env')
            ->where('status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->first();

        $lastCompletedObsAt = $lastCompletedObsTask?->updated_at;
        $obsAgeSeconds = $lastCompletedObsAt ? now()->diffInSeconds(\Carbon\Carbon::parse($lastCompletedObsAt)) : null;
        $obsStale = $obsAgeSeconds === null || $obsAgeSeconds > 180; // Stale if no observation in 3 minutes

        $obsStatus = 'never_run';
        if ($activeTask && $activeTask->type === 'agent.observe_env') {
            $obsStatus = 'running';
        } elseif ($lastObservationTask && $lastObservationTask->status === 'failed') {
            $obsStatus = 'failed';
        } elseif ($lastCompletedObsTask) {
            $obsStatus = 'completed';
        }

        $observationTelemetry = [
            'status' => $obsStatus,
            'target' => $obsTarget,
            'path' => $targetFile,
            'exists' => $targetExists,
            'last_modified' => $targetLastChanged,
            'last_completed_at' => $lastCompletedObsAt,
            'age_seconds' => $obsAgeSeconds,
            'stale' => $obsStale,
            'last_result' => $lastObservedHash ? 'monitored' : 'pending',
            'last_hash' => $lastObservedHash ? substr($lastObservedHash, 0, 16) . '...' : null,
        ];

        // Scanner Engine Telemetry (Real Backend Truth from Scanner Subsystem & Scan History)
        $repositoryScanner = new \App\Services\Scan\RepositoryScanner();
        $scannersCount = count($repositoryScanner->getScanners());

        $lastScan = \App\Models\Scan::orderBy('created_at', 'desc')->first();
        $lastReportTask = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('type', 'agent.report_finding')
            ->orderBy('updated_at', 'desc')
            ->first();

        $scannerEngineStatus = 'UNKNOWN';
        $activeScannerName = null;

        if ($lastScan && $lastScan->status === \App\Enums\Scan\ScanStatus::RUNNING) {
            $scannerEngineStatus = 'RUNNING';
            $activeScannerName = $lastScan->engine instanceof \UnitEnum ? $lastScan->engine->value : ($lastScan->engine ?? null);
        } elseif ($processStatus === 'stopped') {
            $scannerEngineStatus = 'UNAVAILABLE';
        } elseif ($lastScan && $lastScan->status === \App\Enums\Scan\ScanStatus::FAILED) {
            $scannerEngineStatus = 'FAILED';
        } elseif ($lastScan) {
            $scannerEngineStatus = 'IDLE';
        } else {
            $scannerEngineStatus = 'NEVER_RUN';
        }

        $scanTelemetry = [
            'status' => strtolower($scannerEngineStatus),
            'engine_status' => $scannerEngineStatus,
            'scanners_available' => $scannersCount,
            'active_scanner' => $activeScannerName,
            'scanner' => $activeScannerName ?? ($lastScan ? ($lastScan->engine instanceof \UnitEnum ? $lastScan->engine->value : $lastScan->engine) : null),
            'last_completed_at' => $lastScan?->completed_at?->toISOString() ?? null,
            'last_target' => $lastScan?->target ?? null,
            'findings_created' => Finding::where('agent_id', $agentId)->count(),
        ];

        // Queue Telemetry
        $queueCounts = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $queueTelemetry = [
            'pending' => $queueCounts['pending'] ?? 0,
            'processing' => $queueCounts['processing'] ?? 0,
            'completed' => $queueCounts['completed'] ?? 0,
            'failed' => $queueCounts['failed'] ?? 0,
        ];

        // Metrics Telemetry
        // Metrics Telemetry (real authoritative database queries)
        $totalObservations = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('type', 'agent.observe_env')
            ->where('status', 'completed')
            ->count();

        $totalScans = \App\Models\Scan::where('created_by', $agentId)->count();

        $totalTasksProcessed = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        $metricsTelemetry = [
            'observations' => $totalObservations,
            'scans' => $totalScans,
            'findings' => Finding::where('agent_id', $agentId)->count(),
            'tasks_processed' => $totalTasksProcessed,
            'open_findings' => Finding::where('agent_id', $agentId)->whereIn('lifecycle_status', ['new', 'open', 'recurring', 'regression'])->count(),
            'regressions' => Finding::where('agent_id', $agentId)->where('lifecycle_status', 'regression')->count(),
        ];

        // Overall Operational State
        if ($processStatus === 'stopped') {
            $overallState = 'STOPPED';
        } elseif ($processStatus === 'stale') {
            $overallState = 'STALE_HEARTBEAT';
        } elseif ($obsStale) {
            $overallState = 'STALE_OBSERVATION';
        } elseif ($activeTask?->type === 'agent.observe_env') {
            $overallState = 'OBSERVING';
        } elseif ($activeTask?->type === 'agent.report_finding') {
            $overallState = 'REPORTING';
        } elseif ($scannerEngineStatus === 'RUNNING') {
            $overallState = 'SCANNING';
        } else {
            $overallState = 'HEALTHY_IDLE';
        }

        // Recent Scans (real Scan models)
        $recentScans = \App\Models\Scan::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($scan) {
                return [
                    'id' => $scan->id,
                    'uuid' => $scan->uuid,
                    'name' => $scan->name,
                    'target' => $scan->target,
                    'type' => $scan->type instanceof \UnitEnum ? $scan->type->value : $scan->type,
                    'engine' => $scan->engine instanceof \UnitEnum ? $scan->engine->value : $scan->engine,
                    'status' => $scan->status instanceof \UnitEnum ? $scan->status->value : $scan->status,
                    'progress' => $scan->progress,
                    'started_at' => $scan->started_at?->toISOString() ?? $scan->started_at,
                    'completed_at' => $scan->completed_at?->toISOString() ?? $scan->completed_at,
                    'duration' => $scan->duration,
                    'findings_count' => \App\Models\Finding::where('scan_id', $scan->id)->count(),
                ];
            });

        // Real Findings (limit 10)
        $recentFindings = Finding::where('agent_id', $agentId)
            ->orWhereNotNull('agent_id')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($finding) {
                return [
                    'id' => $finding->id,
                    'finding_id' => $finding->finding_id,
                    'title' => $finding->title,
                    'severity' => $finding->severity,
                    'scanner' => $finding->scanner,
                    'target' => $finding->target_id ?? null,
                    'lifecycle_status' => $finding->lifecycle_status instanceof \UnitEnum ? $finding->lifecycle_status->value : $finding->lifecycle_status,
                    'status' => $finding->status,
                    'created_at' => $finding->created_at?->toISOString() ?? $finding->created_at,
                    'fingerprint' => $finding->fingerprint,
                ];
            });

        // Recent Tasks (limit 15)
        $recentTasks = DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($task) {
                $payload = json_decode($task->payload, true);
                $createdAt = $task->created_at ? \Carbon\Carbon::parse($task->created_at)->toISOString() : null;
                $updatedAt = $task->updated_at ? \Carbon\Carbon::parse($task->updated_at)->toISOString() : null;
                $duration = ($task->status === 'completed' || $task->status === 'failed') && $task->created_at && $task->updated_at
                    ? max(0, \Carbon\Carbon::parse($task->updated_at)->diffInSeconds(\Carbon\Carbon::parse($task->created_at)))
                    : null;

                return [
                    'id' => $task->id,
                    'type' => $task->type,
                    'status' => $task->status,
                    'target' => $payload['target'] ?? null,
                    'payload' => $payload,
                    'attempts' => $task->attempts,
                    'started_at' => $createdAt,
                    'completed_at' => ($task->status === 'completed' || $task->status === 'failed') ? $updatedAt : null,
                    'duration_seconds' => $duration,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            });

        // Combined Activity Log Timeline (from tasks, scans, and finding logs)
        $activityEvents = collect();

        // Add task events
        foreach ($recentTasks as $t) {
            $ts = $t['updated_at'] ?? $t['created_at'];
            $activityEvents->push([
                'id' => 'task_' . $t['id'],
                'timestamp' => $ts,
                'event' => $t['type'],
                'details' => 'Status: ' . strtoupper($t['status']) . ($t['target'] ? ' • Target: ' . $t['target'] : ''),
                'type' => 'task',
                'created_at' => $ts,
            ]);
        }

        // Add scan events
        foreach ($recentScans as $s) {
            $ts = $s['completed_at'] ?? $s['started_at'];
            $activityEvents->push([
                'id' => 'scan_' . $s['id'],
                'timestamp' => $ts,
                'event' => $s['name'] ?? 'Scan',
                'details' => 'Engine: ' . strtoupper($s['engine'] ?? 'UNKNOWN') . ' • Status: ' . strtoupper($s['status']) . ' • Findings: ' . $s['findings_count'],
                'type' => 'scan',
                'created_at' => $ts,
            ]);
        }

        // Add finding events
        $agentFindingIds = Finding::where('agent_id', $agentId)->pluck('id');
        $findingLogs = FindingActivityLog::with('finding:id,title,severity,finding_id')
            ->whereIn('finding_id', $agentFindingIds)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($findingLogs as $fl) {
            $ts = $fl->created_at?->toISOString() ?? $fl->created_at;
            $sev = $fl->finding?->severity;
            $sevStr = $sev instanceof \UnitEnum ? $sev->value : ($sev ?? 'LOW');
            $actionLabel = $fl->action ?? 'unknown';

            $activityEvents->push([
                'id' => 'finding_log_' . $fl->id,
                'timestamp' => $ts,
                'event' => $actionLabel,
                'details' => ($fl->finding ? $fl->finding->title : 'Finding #' . $fl->finding_id) . ' (' . strtoupper((string)$sevStr) . ')',
                'type' => 'finding',
                'created_at' => $ts,
            ]);
        }

        $sortedActivity = $activityEvents->filter(fn($e) => !empty($e['timestamp']))
            ->sortByDesc('timestamp')
            ->values()
            ->take(15);

        return response()->json([
            'overall_state' => $overallState,
            'agent' => [
                'id' => $agentId,
                'version' => $health['version'],
                'environment' => 'Local Environment',
                'instance_id' => $health['instance_id'],
            ],
            'process' => [
                'status' => $processStatus,
                'last_heartbeat_at' => $lastHeartbeatAt,
                'heartbeat_age_seconds' => $heartbeatAgeSeconds,
                'started_at' => $health['started_at'],
            ],
            'worker' => $workerTelemetry,
            'observation' => $observationTelemetry,
            'scan' => $scanTelemetry,
            'queue' => $queueTelemetry,
            'metrics' => $metricsTelemetry,
            'health' => $health,
            'active_task' => $activeTask ? [
                'id' => $activeTask->id,
                'type' => $activeTask->type,
                'status' => $activeTask->status,
                'payload' => json_decode($activeTask->payload, true),
                'created_at' => $activeTask->created_at,
                'updated_at' => $activeTask->updated_at,
            ] : null,
            'recent_scans' => $recentScans,
            'recent_tasks' => $recentTasks,
            'recent_findings' => $recentFindings,
            'observation_target' => [
                'target' => $observationTelemetry['target'],
                'path' => $observationTelemetry['path'],
                'exists' => $targetExists,
                'last_modified' => $targetLastChanged,
                'last_checked' => $lastCompletedObsAt,
                'status' => $obsStale ? 'Stale' : ($lastObservedHash ? 'Monitored (Hash Active)' : 'Pending')
            ],
            'activity_timeline' => $sortedActivity,
        ]);
    }
}
