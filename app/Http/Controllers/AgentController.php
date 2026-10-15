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
    public function health(AgentService $agentService, \Illuminate\Http\Request $request): JsonResponse
    {
        $targetAgentId = $request->query('agent_id');
        if ($targetAgentId) {
            $agentService->setAgentId($targetAgentId);
        }

        return response()->json(
            $agentService->getHealthStatus()
        );
    }

    /**
     * Record a heartbeat from a connected local Agent.
     */
    public function heartbeat(\Illuminate\Http\Request $request, AgentService $agentService): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|string|max:128',
            'version' => 'nullable|string|max:32',
            'os' => 'nullable|string|max:64',
            'hostname' => 'nullable|string|max:255',
            'execution_mode' => 'nullable|string|max:32',
            'desktop_picker_supported' => 'nullable|boolean',
        ]);

        $agentId = $validated['agent_id'];
        $timeout = config('agent.heartbeat.timeout', 90);
        $executionMode = $validated['execution_mode'] ?? (($agentId === 'trustnode-local-agent-1') ? 'container' : 'desktop');
        $desktopPickerSupported = $validated['desktop_picker_supported'] ?? ($executionMode === 'desktop');

        Cache::put("trustnode_agent_heartbeat_{$agentId}", now()->timestamp, $timeout);

        $state = [
            'state' => 'running',
            'instance_id' => $validated['hostname'] ?? $agentId,
            'version' => $validated['version'] ?? '1.0.0',
            'os' => $validated['os'] ?? PHP_OS_FAMILY,
            'execution_mode' => $executionMode,
            'desktop_picker_supported' => (bool) $desktopPickerSupported,
            'user_id' => $request->user()?->id,
            'organization_id' => $request->user()?->teams()->first()?->organization_id,
            'started_at' => now()->toIso8601String(),
            'last_heartbeat_at' => now()->toIso8601String(),
        ];

        DB::table('agent_states')->updateOrInsert(
            ['agent_id' => $agentId],
            [
                'state' => json_encode($state),
                'updated_at' => now(),
            ]
        );

        $pendingPicker = Cache::pull("agent_picker_request_{$agentId}");
        $pendingScan = Cache::pull("agent_scan_pending_{$agentId}");

        // Return all currently active projects assigned to this agent for continuous multi-project monitoring
        $assignedProjects = \App\Models\CodebaseProject::query()
            ->where('agent_id', $agentId)
            ->where('status', \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE)
            ->where('continuous_scan_enabled', true)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'path', 'scan_profile', 'continuous_scan_enabled', 'status']);

        return response()->json([
            'status' => 'acknowledged',
            'agent_id' => $agentId,
            'timestamp' => now()->toISOString(),
            'pending_picker_request' => $pendingPicker,
            'pending_scan_request' => $pendingScan,
            'assigned_projects' => $assignedProjects,
        ]);
    }

    /**
     * List known/active agents for selection in the UI.
     */
    public function list(): JsonResponse
    {
        $records = DB::table('agent_states')->get();
        $timeout = config('agent.heartbeat.timeout', 90);

        $agents = $records->map(function ($row) use ($timeout) {
            $state = json_decode($row->state, true) ?? [];
            $cachedHb = Cache::get("trustnode_agent_heartbeat_{$row->agent_id}");
            $staleThreshold = now()->subSeconds($timeout)->timestamp;
            $dbTimestamp = !empty($row->updated_at) ? strtotime((string) $row->updated_at) : 0;
            $effectiveHb = max((int) $cachedHb, $dbTimestamp);
            $isOnline = $effectiveHb >= $staleThreshold;
            $executionMode = $state['execution_mode'] ?? (($row->agent_id === 'trustnode-local-agent-1') ? 'container' : 'desktop');
            $desktopPickerSupported = $state['desktop_picker_supported'] ?? ($executionMode === 'desktop');

            return [
                'agent_id' => $row->agent_id,
                'status' => $isOnline ? 'online' : 'offline',
                'type' => $executionMode,
                'execution_mode' => $executionMode,
                'desktop_picker_supported' => (bool) $desktopPickerSupported,
                'version' => $state['version'] ?? '1.0.0',
                'os' => $state['os'] ?? ($executionMode === 'desktop' ? PHP_OS_FAMILY : 'Linux'),
                'last_heartbeat_at' => $row->updated_at,
            ];
        })->sort(function ($a, $b) {
            // Sort: online first, then desktop picker capable first, then by last_heartbeat
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'online' ? -1 : 1;
            }
            if ($a['desktop_picker_supported'] !== $b['desktop_picker_supported']) {
                return $a['desktop_picker_supported'] ? -1 : 1;
            }
            return strcmp((string)$b['last_heartbeat_at'], (string)$a['last_heartbeat_at']);
        })->values();

        return response()->json([
            'agents' => $agents
        ]);
    }

    /**
     * Download or retrieve an OS-specific Local Agent installer script.
     */
    public function installer(\Illuminate\Http\Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $os = strtolower((string) $request->query('os', 'windows'));
        $baseUrl = url('/');
        $token = $request->user()?->createToken('local-agent-installer', ['*'])->plainTextToken ?? 'YOUR_API_TOKEN';

        if ($os === 'windows' || $os === 'win') {
            $script = <<<PS1
# TrustNode Local Agent Installer (Windows)
\$ErrorActionPreference = "Stop"

Write-Host "===============================================" -ForegroundColor Cyan
Write-Host " TrustNode Local Agent Setup " -ForegroundColor Cyan
Write-Host "===============================================" -ForegroundColor Cyan

\$trustNodeDir = "\$HOME\\.trustnode"
if (-not (Test-Path \$trustNodeDir)) {
    New-Item -ItemType Directory -Path \$trustNodeDir -Force | Out-Null
}

# 1. Detect PHP runtime
\$php = (Get-Command php -ErrorAction SilentlyContinue)?.Source
if (-not \$php -and (Test-Path "C:\\xampp\\php\\php.exe")) {
    \$php = "C:\\xampp\\php\\php.exe"
}

if (-not \$php) {
    Write-Host "[ERROR] PHP 8.2+ runtime not detected on your system." -ForegroundColor Red
    Write-Host "Please install PHP (or XAMPP) and add it to your PATH before proceeding." -ForegroundColor Yellow
    Exit 1
}

Write-Host "[OK] Using PHP Runtime: \$php" -ForegroundColor Green

# 2. Write Agent configuration
\$configFile = "\$trustNodeDir\\config"
\$configJson = @{
    server = "{$baseUrl}"
    token = "{$token}"
    agent_id = "agent-desktop-" + [guid]::NewGuid().ToString().Substring(0, 8)
} | ConvertTo-Json

Set-Content -Path \$configFile -Value \$configJson -Force
Write-Host "[OK] Configured TrustNode connection to {$baseUrl}" -ForegroundColor Green

# 3. Download TrustNode CLI bundle if not local
\$cliScript = "\$trustNodeDir\\trustnode.php"
try {
    Invoke-WebRequest -Uri "{$baseUrl}/api/agent/cli-script" -OutFile \$cliScript -UseBasicParsing -ErrorAction SilentlyContinue
} catch {}

Write-Host "`nTrustNode Local Agent is configured and ready." -ForegroundColor Cyan
Write-Host "To start background auto-start daemon, run:" -ForegroundColor Yellow
Write-Host "  trustnode agent:service install" -ForegroundColor White
PS1;
            return response($script, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="install-agent.ps1"',
            ]);
        }

        // Unix (macOS / Linux)
        $script = <<<SH
#!/usr/bin/env bash
set -e

echo "==============================================="
echo " TrustNode Local Agent Setup (macOS / Linux) "
echo "==============================================="

TRUSTNODE_DIR="\$HOME/.trustnode"
mkdir -p "\$TRUSTNODE_DIR"

HOST_PHP=\$(command -v php 2>/dev/null)
if [ -z "\$HOST_PHP" ]; then
    echo "[ERROR] PHP 8.2+ runtime not detected on your system." >&2
    echo "Please install PHP before proceeding." >&2
    exit 1
fi

echo "[OK] Using PHP Runtime: \$HOST_PHP"

CONFIG_FILE="\$TRUSTNODE_DIR/config"
cat > "\$CONFIG_FILE" << EOF
{
    "server": "{$baseUrl}",
    "token": "{$token}",
    "agent_id": "agent-host-\$(head -c 4 /dev/urandom | xxd -p)"
}
EOF

chmod 600 "\$CONFIG_FILE"
echo "[OK] Configured TrustNode connection to {$baseUrl}"
echo "To start background auto-start daemon, run:"
echo "  trustnode agent:service install"
SH;

        return response($script, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="install-agent.sh"',
        ]);
    }

    /**
     * Request the active local Agent to open the native folder picker.
     */
    public function pickFolderRequest(\Illuminate\Http\Request $request): JsonResponse
    {
        $timeout = config('agent.heartbeat.timeout', 90);
        $staleThreshold = now()->subSeconds($timeout)->timestamp;
        $requestedAgentId = $request->input('agent_id');

        // Retrieve active agents that have an active heartbeat
        $activeAgents = DB::table('agent_states')->get()->filter(function ($row) use ($staleThreshold) {
            $cachedHb = Cache::get("trustnode_agent_heartbeat_{$row->agent_id}");
            $dbTimestamp = !empty($row->updated_at) ? strtotime((string) $row->updated_at) : 0;
            $effectiveHb = max((int) $cachedHb, $dbTimestamp);
            return $effectiveHb >= $staleThreshold;
        })->values();

        if ($activeAgents->isEmpty()) {
            return response()->json([
                'message' => 'No active Local Agent connected. Please start your agent in terminal on your computer with `trustnode agent:watch`.'
            ], 503);
        }

        $userId = $request->user()?->id;
        $userOrgId = $request->user()?->teams()->first()?->organization_id;

        // If a specific agent_id was requested by the user, verify it exists and supports desktop picking
        $chosenAgent = null;
        if ($requestedAgentId) {
            $candidate = $activeAgents->firstWhere('agent_id', $requestedAgentId);
            if ($candidate) {
                $candidateState = json_decode($candidate->state, true) ?? [];
                $canPick = ($candidateState['desktop_picker_supported'] ?? false) || (($candidate->agent_id !== 'trustnode-local-agent-1') && (($candidateState['execution_mode'] ?? 'desktop') === 'desktop'));
                if ($canPick) {
                    $chosenAgent = $candidate;
                } else {
                    return response()->json([
                        'message' => "The selected Agent ({$requestedAgentId}) is running in container/service mode and cannot display native desktop dialogs. Start the desktop agent on your host with `php cli/bin/trustnode agent:watch`."
                    ], 422);
                }
            }
        }

        // Auto-select eligible host desktop agent: strictly desktop-capable
        if (!$chosenAgent) {
            $desktopAgents = $activeAgents->filter(function ($row) use ($userId, $userOrgId) {
                $st = json_decode($row->state, true) ?? [];
                $canPick = ($st['desktop_picker_supported'] ?? false) || (($row->agent_id !== 'trustnode-local-agent-1') && (($st['execution_mode'] ?? 'desktop') === 'desktop'));
                if (!$canPick) {
                    return false;
                }
                // If agent recorded a user_id or organization_id, check for tenant match
                if ($userId && isset($st['user_id']) && $st['user_id'] != $userId) {
                    if ($userOrgId && isset($st['organization_id']) && $st['organization_id'] != $userOrgId) {
                        return false;
                    }
                }
                return true;
            })->sortByDesc('updated_at')->values();

            if ($desktopAgents->isNotEmpty()) {
                $chosenAgent = $desktopAgents->first();
            } else {
                return response()->json([
                    'message' => 'No interactive desktop Agent is connected. Please run `php cli/bin/trustnode agent:watch` in terminal on your computer.'
                ], 422);
            }
        }

        $requestId = 'pfr_' . bin2hex(random_bytes(8));

        // Signal agent to trigger folder picker dialog (TTL: 60s)
        Cache::put("agent_picker_request_{$chosenAgent->agent_id}", [
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'status' => 'pending',
            'created_at' => now()->timestamp,
        ], 60);

        // Store request metadata for verification
        Cache::put("agent_picker_meta_{$requestId}", [
            'user_id' => $request->user()?->id,
            'agent_id' => $chosenAgent->agent_id,
            'created_at' => now()->timestamp,
        ], 120);

        return response()->json([
            'request_id' => $requestId,
            'agent_id' => $chosenAgent->agent_id,
            'message' => 'Folder picker request initiated on agent.'
        ]);
    }

    /**
     * Report or check folder picker selection status.
     */
    public function pickFolderStatus(\Illuminate\Http\Request $request): JsonResponse
    {
        $requestId = $request->input('request_id');
        $agentId = $request->input('agent_id');

        if ($request->isMethod('post')) {
            // Agent posting the selected path back
            $validated = $request->validate([
                'request_id' => 'required|string',
                'agent_id' => 'required|string',
                'path' => 'nullable|string',
                'canceled' => 'nullable|boolean',
            ]);

            // Validate that request_id was actually issued to this agent
            $meta = Cache::get("agent_picker_meta_{$validated['request_id']}");
            if (!$meta || $meta['agent_id'] !== $validated['agent_id']) {
                return response()->json(['message' => 'Invalid or expired folder picker request.'], 403);
            }

            $path = $validated['path'] ?? null;
            $canceled = (bool) ($validated['canceled'] ?? false);

            if ($path !== null && !$canceled) {
                // Reject path traversal
                if (str_contains($path, '..') || str_contains($path, "\0")) {
                    return response()->json(['message' => 'Invalid path.'], 422);
                }

                // Disallow scanning system roots, drive roots, or critical parent paths
                $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
                if (preg_match('/^[a-zA-Z]:$/', $normalizedPath) || $normalizedPath === '' || $normalizedPath === '/' || $normalizedPath === '/etc' || $normalizedPath === '/root' || preg_match('/^[a-zA-Z]:[\/\\\\]Windows/i', $path)) {
                    return response()->json(['message' => 'Authorizing system root, entire drives, or critical OS directories is not permitted.'], 422);
                }
            }

            Cache::put("agent_picker_result_{$validated['request_id']}", [
                'path' => $path,
                'canceled' => $canceled,
                'agent_id' => $validated['agent_id'],
                'completed_at' => now()->timestamp,
            ], 120);

            return response()->json(['status' => 'acknowledged']);
        }

        // Browser polling status
        if (!$requestId) {
            return response()->json(['message' => 'Missing request_id parameter.'], 422);
        }

        $meta = Cache::get("agent_picker_meta_{$requestId}");
        if ($meta && isset($meta['user_id']) && $request->user()?->id && $meta['user_id'] !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized request correlation.'], 403);
        }

        $result = Cache::get("agent_picker_result_{$requestId}");
        if ($result) {
            return response()->json([
                'status' => 'completed',
                'result' => $result
            ]);
        }

        return response()->json([
            'status' => 'pending'
        ]);
    }

    /**
     * Get operational data for the Agent Console UI.
     */
    public function console(AgentService $agentService, \Illuminate\Http\Request $request): JsonResponse
    {
        $targetAgentId = $request->query('agent_id');
        if (!$targetAgentId) {
            // Find the most recently active host/desktop agent with an active heartbeat
            $timeout = config('agent.heartbeat.timeout', 90);
            $staleThreshold = now()->subSeconds($timeout)->timestamp;
            $activeHostAgent = DB::table('agent_states')->get()->filter(function ($row) use ($staleThreshold) {
                $cachedHb = Cache::get("trustnode_agent_heartbeat_{$row->agent_id}");
                $dbTimestamp = !empty($row->updated_at) ? strtotime((string) $row->updated_at) : 0;
                $effectiveHb = max((int) $cachedHb, $dbTimestamp);
                if ($effectiveHb < $staleThreshold) {
                    return false;
                }
                $state = json_decode($row->state, true) ?? [];
                return ($state['execution_mode'] ?? '') === 'desktop' || $row->agent_id !== 'trustnode-local-agent-1';
            })->sortByDesc('updated_at')->first();

            if ($activeHostAgent) {
                $targetAgentId = $activeHostAgent->agent_id;
            }
        }

        if ($targetAgentId) {
            $agentService->setAgentId($targetAgentId);
        }

        $health = $agentService->getHealthStatus();
        $agentId = $health['agent_id'];
        $heartbeatTimeout = config('agent.heartbeat.timeout', 90);

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

        // Add agent activity logs
        $agentLogs = \App\Models\AgentActivityLog::where('agent_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        foreach ($agentLogs as $al) {
            $ts = $al->created_at?->toISOString() ?? $al->created_at;
            $actionLabel = $al->action ?? 'unknown';

            $activityEvents->push([
                'id' => 'agent_log_' . $al->id,
                'timestamp' => $ts,
                'event' => strtoupper($actionLabel),
                'details' => $al->properties['message'] ?? 'Agent Lifecycle Event',
                'type' => 'agent',
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
            'monitored_projects' => \App\Models\CodebaseProject::query()
                ->where('agent_id', $agentId)
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'path', 'scan_profile', 'continuous_scan_enabled', 'status', 'created_at', 'updated_at']),
            'exclusion_policy' => [
                'rules' => [
                    '.env*',
                    '*.pem',
                    '*.key',
                    'id_rsa*',
                    'node_modules',
                    'vendor',
                    '.git',
                    '*.exe',
                    '*.dll',
                    '*.zip'
                ],
                'description' => 'Local Agent excludes environment secrets, private keys, dependencies, and binary artifacts from transmission.',
            ],
            'activity_timeline' => $sortedActivity,
        ]);
    }

    /**
     * Record an activity event from the Local Agent.
     */
    public function recordActivity(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|string|max:128',
            'action' => 'required|string|max:64',
            'message' => 'required|string|max:500',
            'properties' => 'nullable|array',
        ]);

        $properties = $validated['properties'] ?? [];
        $properties['message'] = $validated['message'];

        \App\Models\AgentActivityLog::create([
            'agent_id' => $validated['agent_id'],
            'action' => strtolower($validated['action']),
            'properties' => $properties,
            'user_id' => $request->user()?->id,
            'organization_id' => $request->user()?->teams()->first()?->organization_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['status' => 'recorded']);
    }
}
