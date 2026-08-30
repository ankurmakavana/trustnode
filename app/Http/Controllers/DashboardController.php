<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get aggregate statistics and security posture intelligence.
     */
    public function stats(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $repositoryId = $request->filled('repository_id') ? (int) $request->input('repository_id') : null;
        $localProjectId = $request->filled('local_project_id') ? (int) $request->input('local_project_id') : null;
        $targetId = $request->filled('target_id') ? (int) $request->input('target_id') : null;

        $data = $dashboardService->getStats($repositoryId, $localProjectId, $targetId);

        return response()->json($data);
    }
}
