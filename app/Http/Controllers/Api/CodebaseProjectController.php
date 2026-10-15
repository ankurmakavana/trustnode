<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AgentQueueInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\CodebaseProject\StoreCodebaseProjectRequest;
use App\Http\Resources\CodebaseProject\CodebaseProjectResource;
use App\Models\CodebaseProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CodebaseProjectController extends Controller
{
    /**
     * Get available workspace directories inside the configured mount.
     */
    public function workspaceDirectories(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('targets.view')) {
            abort(403, 'Unauthorized.');
        }

        $scanMountDir = config('agent.scan_mount_dir', '/mnt/trustnode-scan');
        $canonicalMount = realpath($scanMountDir);

        if ($canonicalMount === false || !is_dir($canonicalMount)) {
            return response()->json([
                'message' => 'Workspace mount is unavailable or not configured correctly.',
                'directories' => []
            ], 503);
        }

        $directories = [];
        try {
            $iterator = new \DirectoryIterator($canonicalMount);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isDot() || !$fileinfo->isDir()) {
                    continue;
                }
                
                $dirPath = $fileinfo->getRealPath();
                // Ensure no symlink escapes outside the mount
                if ($dirPath !== false && str_starts_with($dirPath, $canonicalMount . DIRECTORY_SEPARATOR)) {
                    // Return relative path
                    $relativePath = trim(substr($dirPath, strlen($canonicalMount)), DIRECTORY_SEPARATOR);
                    if ($relativePath !== '') {
                        $directories[] = str_replace('\\', '/', $relativePath);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to read workspace directories: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to read workspace directories.',
                'directories' => []
            ], 500);
        }

        sort($directories);

        return response()->json([
            'directories' => $directories
        ]);
    }

    /**
     * List registered projects for the authenticated user's organization.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if (!$request->user()->hasPermission('targets.view')) {
            abort(403, 'Unauthorized.');
        }

        $projects = CodebaseProject::query()->latest()->get();

        return CodebaseProjectResource::collection($projects);
    }

    /**
     * Register a new codebase project.
     */
    public function store(StoreCodebaseProjectRequest $request): CodebaseProjectResource|JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $sourceType = $validated['source_type'] ?? null;
        if ($sourceType === null) {
            $pathInput = (string) ($validated['path'] ?? '');
            // If path looks like an absolute path (Windows drive letter or Unix root), default to agent
            if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $pathInput) || str_starts_with($pathInput, '/')) {
                $sourceType = 'agent';
            } else {
                $sourceType = 'workspace_mount';
            }
        }
        $agentId = $validated['agent_id'] ?? null;

        $orgId = \App\Models\Team::query()
            ->whereHas('users', fn($q) => $q->whereKey($user->id))
            ->value('organization_id');

        if (!$orgId) {
            return response()->json(['message' => 'User is not associated with an organization.'], 403);
        }

        if ($sourceType === 'agent') {
            // Persistent Local Agent Project
            // Path must not contain path traversal (..) or null bytes
            $rawPath = $validated['path'];
            if (str_contains($rawPath, '..') || str_contains($rawPath, "\0")) {
                return response()->json(['message' => 'Path traversal sequences are not permitted.'], 422);
            }

            // Path containment: Disallow scanning system roots, drive roots, or critical parent paths
            $normalizedPath = rtrim(str_replace('\\', '/', $rawPath), '/');
            if (preg_match('/^[a-zA-Z]:$/', $normalizedPath) || $normalizedPath === '' || $normalizedPath === '/' || $normalizedPath === '/etc' || $normalizedPath === '/root' || preg_match('/^[a-zA-Z]:[\/\\\\]Windows/i', $rawPath)) {
                return response()->json(['message' => 'Authorizing system root, entire drives, or critical OS directories is not permitted.'], 422);
            }

            // Automatic Agent Resolution if not explicitly supplied
            if (empty($agentId)) {
                $timeout = config('agent.heartbeat.timeout', 90);
                $staleThreshold = now()->subSeconds($timeout)->timestamp;
                $activeAgents = DB::table('agent_states')->get()->filter(function ($row) use ($staleThreshold) {
                    $cachedHb = \Illuminate\Support\Facades\Cache::get("trustnode_agent_heartbeat_{$row->agent_id}");
                    $dbTimestamp = !empty($row->updated_at) ? strtotime((string) $row->updated_at) : 0;
                    $effectiveHb = max((int) $cachedHb, $dbTimestamp);
                    return $effectiveHb >= $staleThreshold;
                })->values();

                $userId = $user->id;
                $desktopAgents = $activeAgents->filter(function ($r) use ($userId, $orgId) {
                    $st = json_decode($r->state, true) ?? [];
                    $isDesktop = ($st['desktop_picker_supported'] ?? false) || (($st['execution_mode'] ?? 'desktop') === 'desktop' && $r->agent_id !== 'trustnode-local-agent-1');
                    if (!$isDesktop) {
                        return false;
                    }
                    if ($userId && isset($st['user_id']) && $st['user_id'] != $userId) {
                        if ($orgId && isset($st['organization_id']) && $st['organization_id'] != $orgId) {
                            return false;
                        }
                    }
                    return true;
                })->values();
                if ($desktopAgents->isNotEmpty()) {
                    $agentId = $desktopAgents->sortByDesc('updated_at')->first()->agent_id;
                } else {
                    return response()->json([
                        'message' => 'No active host Local Agent is currently connected. Please start your agent with `php cli/bin/trustnode agent:watch`.'
                    ], 422);
                }
            } else {
                if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $agentId)) {
                    return response()->json(['message' => 'Invalid Agent ID format.'], 422);
                }

                // Verify the agent exists and has sent a valid heartbeat
                $timeout = config('agent.heartbeat.timeout', 90);
                $staleThreshold = now()->subSeconds($timeout)->timestamp;
                $cachedHb = \Illuminate\Support\Facades\Cache::get("trustnode_agent_heartbeat_{$agentId}");
                $agentState = DB::table('agent_states')->where('agent_id', $agentId)->first();
                $dbTimestamp = ($agentState && !empty($agentState->updated_at)) ? strtotime((string) $agentState->updated_at) : 0;
                $effectiveHb = max((int) $cachedHb, $dbTimestamp);

                if (!$agentState || $effectiveHb < $staleThreshold) {
                    return response()->json([
                        'message' => "The selected Agent ({$agentId}) is offline or unverified. Please ensure it is running."
                    ], 422);
                }

                $stateData = json_decode($agentState->state, true) ?? [];
                $isDesktop = ($stateData['desktop_picker_supported'] ?? false) || (($stateData['execution_mode'] ?? 'desktop') === 'desktop' && $agentId !== 'trustnode-local-agent-1');
                if (!$isDesktop) {
                    return response()->json([
                        'message' => "The selected Agent ({$agentId}) is running in container mode. A host desktop agent is required to monitor local directories."
                    ], 422);
                }
            }
        } else {
            // Workspace mount project
            $scanMountDir = config('agent.scan_mount_dir', '/mnt/trustnode-scan');
            $canonicalMount = realpath($scanMountDir);

            if ($canonicalMount === false || !is_dir($canonicalMount)) {
                return response()->json(['message' => 'Workspace mount is unavailable.'], 503);
            }

            // Validate path resolves inside workspace
            $targetPath = $canonicalMount . DIRECTORY_SEPARATOR . ltrim($validated['path'], '/\\');
            $canonicalTarget = realpath($targetPath);

            if ($canonicalTarget === false || !is_dir($canonicalTarget)) {
                return response()->json(['message' => 'The specified path does not exist or is not a directory.'], 422);
            }

            // The boundary must end at a separator so "<mount>-sibling" is not treated as inside the mount.
            if ($canonicalTarget !== $canonicalMount && !str_starts_with($canonicalTarget, $canonicalMount . DIRECTORY_SEPARATOR)) {
                return response()->json(['message' => 'The specified path resolves outside the allowed workspace mount.'], 422);
            }
        }

        $existingTrashed = CodebaseProject::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('path', $validated['path'])
            ->first();

        if ($existingTrashed) {
            $existingTrashed->restore();
            $existingTrashed->update([
                'name' => $validated['name'],
                'source_type' => $sourceType,
                'agent_id' => $agentId,
                'scan_profile' => $validated['scan_profile'],
                'continuous_scan_enabled' => $validated['continuous_scan_enabled'],
                'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE,
                'created_by' => $user->id,
            ]);
            $project = $existingTrashed;
        } else {
            $project = CodebaseProject::create([
                'name' => $validated['name'],
                'path' => $validated['path'],
                'source_type' => $sourceType,
                'agent_id' => $agentId,
                'scan_profile' => $validated['scan_profile'],
                'continuous_scan_enabled' => $validated['continuous_scan_enabled'],
                'organization_id' => $orgId,
                'created_by' => $user->id,
            ]);
        }

        // If an agent is linked, attempt to dispatch initial scan request automatically
        if ($sourceType === 'agent' && $agentId) {
            $scanPayload = [
                'project_id' => $project->id,
                'target_path' => $project->path,
                'name' => $project->name,
                'scan_profile' => $project->scan_profile?->value ?? 'standard',
            ];

            // 1. Direct agent daemon signal via heartbeat channel
            \Illuminate\Support\Facades\Cache::put("agent_scan_pending_{$agentId}", $scanPayload, 300);

            // 2. Queue for agent capability worker
            try {
                $queue = app(AgentQueueInterface::class);
                $queue->enqueue($agentId, 'agent.capability.execute', [
                    'capability_id' => 'agent:scan_codebase',
                    'arguments' => $scanPayload,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Could not auto-enqueue initial scan for project {$project->id}: " . $e->getMessage());
            }
        }

        return new CodebaseProjectResource($project);
    }

    /**
     * Soft-revoke/archive a project.
     */
    public function destroy(Request $request, CodebaseProject $project): JsonResponse
    {
        if (!$request->user()->hasPermission('targets.delete')) {
            abort(403, 'Unauthorized.');
        }

        // Project is isolated by TenantScope globally, so it's already guaranteed to belong to user's org.
        $project->update(['status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ARCHIVED]);
        $project->delete();

        return response()->json(['message' => 'Project archived and access revoked.']);
    }

    /**
     * Manually trigger a scan for the project.
     */
    public function scan(Request $request, CodebaseProject $project, AgentQueueInterface $queue): JsonResponse
    {
        if (!$request->user()->hasPermission('scans.execute')) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE) {
            return response()->json(['message' => 'Project is inactive or revoked.'], 422);
        }

        $targetAgentId = null;
        $scanArguments = [];

        if ($project->source_type === 'agent') {
            $targetAgentId = $project->agent_id;
            if (!$targetAgentId) {
                // Find any running agent
                $activeAgent = DB::table('agent_states')
                    ->where('state->state', 'running')
                    ->first();
                $targetAgentId = $activeAgent?->agent_id;
            }

            if (!$targetAgentId) {
                return response()->json(['message' => 'No active agent found to process the scan.'], 503);
            }

            $scanArguments = [
                'project_id' => $project->id,
                'target_path' => $project->path,
            ];
        } else {
            $scanMountDir = config('agent.scan_mount_dir', '/mnt/trustnode-scan');
            $canonicalMount = realpath($scanMountDir);

            if ($canonicalMount === false || !is_dir($canonicalMount)) {
                return response()->json(['message' => 'Workspace mount is unavailable.'], 503);
            }

            $targetPath = $canonicalMount . DIRECTORY_SEPARATOR . ltrim($project->path, '/\\');
            $canonicalTarget = realpath($targetPath);

            if ($canonicalTarget === false || !is_dir($canonicalTarget)
                || ($canonicalTarget !== $canonicalMount && !str_starts_with($canonicalTarget, $canonicalMount . DIRECTORY_SEPARATOR))) {
                return response()->json(['message' => 'The project path is invalid or no longer exists.'], 422);
            }

            $activeAgent = DB::table('agent_states')
                ->where('state->state', 'running')
                ->first();

            if (!$activeAgent) {
                return response()->json(['message' => 'No active agent found to process the scan.'], 503);
            }

            $targetAgentId = $activeAgent->agent_id;
            $scanArguments = [
                'target_path' => $canonicalTarget,
                'project_id' => $project->id,
            ];
        }

        try {
            $queue->enqueue($targetAgentId, 'agent.capability.execute', [
                'capability_id' => 'agent:scan_codebase',
                'arguments' => $scanArguments,
            ]);
            
            return response()->json(['message' => 'Scan queued successfully.']);
        } catch (\App\Exceptions\QueueFullException $e) {
            return response()->json(['message' => 'Agent queue is full. Please try again later.'], 503);
        }
    }
}
