<?php

namespace App\Http\Controllers\Repository;

use App\Http\Controllers\Controller;
use App\Models\Repository;
use App\Models\Scan;
use App\Enums\Scan\ScanType;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Services\Repository\RepositoryService;
use App\Jobs\ScanRepositoryJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepositoryController extends Controller
{
    protected RepositoryService $repositoryService;

    public function __construct(RepositoryService $repositoryService)
    {
        $this->repositoryService = $repositoryService;
    }

    public function index()
    {
        return response()->json(Repository::orderBy('created_at', 'desc')->get());
    }

    public function show(Repository $repository)
    {
        return response()->json($repository);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'repository_url' => 'required|url',
            'visibility' => 'required|in:public,private',
            'token' => 'nullable|string',
        ]);

        try {
            $repository = $this->repositoryService->connect($data, Auth::id() ?? 1);
            return response()->json($repository, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function validateAccess(Request $request)
    {
        $data = $request->validate([
            'repository_url' => 'required|url',
            'token' => 'nullable|string',
        ]);

        $isValid = $this->repositoryService->githubProvider->validateAccess(
            $data['repository_url'],
            $data['token'] ?? null
        );

        return response()->json(['valid' => $isValid]);
    }

    public function scan(Repository $repository)
    {
        // Prevent concurrent scanning if already queued/running
        $activeScan = Scan::where('target', $repository->name)
            ->whereIn('status', [ScanStatus::QUEUED, ScanStatus::RUNNING])
            ->first();

        if ($activeScan) {
            return response()->json([
                'message' => 'A scan is already active for this repository.',
            ], 422);
        }

        $scan = Scan::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => "Static Code Analysis - " . $repository->name,
            'description' => "Vulnerability scan on branch: " . $repository->default_branch,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $repository->name,
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => Auth::id() ?? 1,
        ]);

        ScanRepositoryJob::dispatch($scan, $repository);

        return response()->json($scan, 202);
    }

    public function scans(Repository $repository)
    {
        $scans = Scan::where('target', $repository->name)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($scans);
    }

    public function destroy(Repository $repository)
    {
        $repository->delete();
        return response()->json(['message' => 'Repository disconnected successfully.']);
    }
}
