<?php

namespace App\Http\Controllers\Repository;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Http\Controllers\Controller;
use App\Jobs\ScanRepositoryJob;
use App\Models\Repository;
use App\Models\Scan;
use App\Services\Repository\RepositoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RepositoryController extends Controller
{
    public function __construct(protected RepositoryService $repositoryService) {}

    public function index()
    {
        return response()->json(Repository::with(['latestScan' => function ($query) {
            $query->withCount('findings');
        }])->orderBy('created_at', 'desc')->get());
    }

    public function show(Repository $repository)
    {
        return response()->json($repository);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'repository_url' => 'required|url|max:2048',
            'visibility' => 'required|in:public,private',
            'token' => 'nullable|string|max:512',
        ]);

        try {
            $repository = $this->repositoryService->connect($data, Auth::id() ?? 1);

            return response()->json($repository, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            // Log technical detail, return clean message to client
            Log::warning('Repository connection failed', [
                'url' => $data['repository_url'],
                'reason' => $e->getMessage(),
                // Never log token
            ]);

            return response()->json([
                'message' => 'Unable to access repository. Please check the repository URL and GitHub credentials.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Repository connection unexpected error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function validateAccess(Request $request)
    {
        $data = $request->validate([
            'repository_url' => 'required|url|max:2048',
            'token' => 'nullable|string|max:512',
        ]);

        try {
            $isValid = $this->repositoryService->githubProvider->validateAccess(
                $data['repository_url'],
                $data['token'] ?? null
            );

            return response()->json(['valid' => $isValid]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['valid' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::warning('Repository validateAccess error', ['error' => $e->getMessage()]);

            return response()->json(['valid' => false], 200);
        }
    }

    public function scan(Repository $repository)
    {
        // Clean up stale scans (e.g., if a worker crashed)
        Scan::where('repository_id', $repository->id)
            ->whereIn('status', [ScanStatus::QUEUED, ScanStatus::RUNNING])
            ->where('updated_at', '<', now()->subMinutes(60))
            ->update([
                'status' => ScanStatus::FAILED,
                'completed_at' => now(),
            ]);

        $scan = DB::transaction(function () use ($repository) {
            // Lock the repository row to prevent race conditions
            Repository::where('id', $repository->id)->lockForUpdate()->first();

            // Prevent concurrent scanning if already queued/running
            $activeScan = Scan::where('repository_id', $repository->id)
                ->whereIn('status', [ScanStatus::QUEUED, ScanStatus::RUNNING])
                ->lockForUpdate()
                ->first();

            if ($activeScan) {
                return null;
            }

            return Scan::create([
                'uuid' => (string) Str::uuid(),
                'repository_id' => $repository->id,
                'name' => 'Static Code Analysis - '.$repository->name,
                'description' => 'Vulnerability scan on branch: '.$repository->default_branch,
                'type' => ScanType::REPOSITORY,
                'engine' => ScanEngine::REPOSITORY_SCANNER,
                'target' => $repository->name,
                'status' => ScanStatus::QUEUED,
                'progress' => 0,
                'created_by' => Auth::id() ?? 1,
            ]);
        });

        if (! $scan) {
            return response()->json([
                'message' => 'A scan is already active for this repository.',
            ], 422);
        }

        ScanRepositoryJob::dispatch($scan, $repository);

        return response()->json($scan, 202);
    }

    public function scans(Repository $repository)
    {
        $scans = $repository->scans()->orderBy('created_at', 'desc')->get();

        return response()->json($scans);
    }

    public function destroy(Repository $repository)
    {
        $repository->delete();

        return response()->json(['message' => 'Repository disconnected successfully.']);
    }
}
