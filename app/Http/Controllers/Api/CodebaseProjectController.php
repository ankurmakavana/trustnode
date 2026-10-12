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

        $orgId = \App\Models\Team::query()
            ->whereHas('users', fn($q) => $q->whereKey($user->id))
            ->value('organization_id');

        if (!$orgId) {
            return response()->json(['message' => 'User is not associated with an organization.'], 403);
        }

        $project = CodebaseProject::create([
            'name' => $validated['name'],
            'path' => $validated['path'],
            'scan_profile' => $validated['scan_profile'],
            'continuous_scan_enabled' => $validated['continuous_scan_enabled'],
            'organization_id' => $orgId,
            'created_by' => $user->id,
        ]);

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

        try {
            $queue->enqueue($activeAgent->agent_id, 'agent.capability.execute', [
                'capability_id' => 'agent:scan_codebase',
                'arguments' => ['target_path' => $canonicalTarget],
            ]);
            
            return response()->json(['message' => 'Scan queued successfully.']);
        } catch (\App\Exceptions\QueueFullException $e) {
            return response()->json(['message' => 'Agent queue is full. Please try again later.'], 503);
        }
    }
}
