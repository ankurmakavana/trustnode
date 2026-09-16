<?php

namespace App\Http\Controllers;

use App\Services\AgentService;
use Illuminate\Http\JsonResponse;

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
}
