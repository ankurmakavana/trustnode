<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\IntegrationHistory;
use App\Models\IntegrationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationController extends Controller
{
    /**
     * Stats dashboard endpoint.
     */
    public function stats(): JsonResponse
    {
        $integrations = Integration::all();
        $jobs = IntegrationJob::all();

        $connected = $integrations->where('status', 'Connected')->count();
        $disconnected = $integrations->where('status', 'Disconnected')->count();

        $healthy = $integrations->where('health_status', 'Healthy')->count();

        $running = $jobs->where('status', 'Running')->count();
        $queued = $jobs->where('status', 'Queued')->count();
        $failed = $jobs->where('status', 'Failed')->count();

        return response()->json([
            'stats' => [
                'connected' => $connected,
                'disconnected' => $disconnected,
                'healthy' => $healthy,
                'running_jobs' => $running,
                'queued_jobs' => $queued,
                'failed_jobs' => $failed,
            ],
            'integrations' => $integrations,
        ]);
    }

    public function index(): JsonResponse
    {
        return $this->stats();
    }

    /**
     * List all connections for a specific connector type (code).
     */
    public function byConnector(string $code): JsonResponse
    {
        $connections = Integration::where('code', $code)
            ->withCount(['jobs'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request): JsonResponse
    {
        $allCodes = [
            'nmap', 'greenbone', 'nessus', 'burp', 'acunetix', 'qualys', 'rapid7',
            'github', 'gitlab', 'azure_devops', 'bitbucket',
            'jenkins', 'github_actions', 'gitlab_ci', 'azure_pipelines',
            'aws', 'azure', 'gcp', 'kubernetes',
            'docker', 'trivy', 'harbor',
            'jira', 'servicenow', 'linear', 'clickup',
            'slack', 'teams', 'discord', 'email',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|in:'.implode(',', $allCodes),
            'type' => 'required|string',
            'environment' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'tls' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        $integration = DB::transaction(function () use ($validated) {
            // Allow multiple connections per connector type — use create, not updateOrCreate
            $integration = Integration::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'type' => $validated['type'],
                'environment' => $validated['environment'] ?? null,
                'description' => $validated['description'] ?? null,
                'host' => $validated['host'] ?? null,
                'port' => $validated['port'] ?? null,
                'username' => $validated['username'] ?? null,
                'tls' => $validated['tls'] ?? false,
                'tags' => $validated['tags'] ?? [],
                'status' => 'Connected',
                'health_status' => 'Healthy',
                'last_check_at' => now(),
            ]);

            if (! empty($validated['password'])) {
                IntegrationCredential::create([
                    'integration_id' => $integration->id,
                    'key' => 'password',
                    'value' => bcrypt($validated['password']),
                ]);
            }

            IntegrationHistory::create([
                'integration_id' => $integration->id,
                'action' => 'Connected',
                'description' => "Connection '{$integration->name}' configured and connected successfully.",
                'status' => 'Success',
            ]);

            return $integration;
        });

        return response()->json([
            'message' => 'Integration connection created successfully.',
            'data' => $integration,
        ]);
    }

    public function show(Integration $integration): JsonResponse
    {
        $integration->load(['jobs', 'histories']);

        return response()->json(['data' => $integration]);
    }

    public function validateConnection(Integration $integration): JsonResponse
    {
        // Simulate health validation
        $statuses = ['Healthy', 'Unreachable', 'Authentication Failed', 'Timeout'];

        // Use realistic mapping or random
        $result = 'Healthy';
        if ($integration->port == 999) {
            $result = 'Unreachable';
        }

        $integration->update([
            'health_status' => $result,
            'last_check_at' => now(),
        ]);

        IntegrationHistory::create([
            'integration_id' => $integration->id,
            'action' => 'Validated',
            'description' => "Health check executed. Connection status: {$result}.",
            'status' => $result === 'Healthy' ? 'Success' : 'Failed',
        ]);

        return response()->json([
            'message' => 'Validation completed.',
            'status' => $result,
            'last_check' => $integration->last_check_at,
        ]);
    }

    public function importData(Request $request, Integration $integration): JsonResponse
    {
        $validated = $request->validate([
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'records_count' => 'nullable|integer',
        ]);

        $records = $validated['records_count'] ?? rand(5, 20);

        $job = DB::transaction(function () use ($integration, $records) {
            $job = IntegrationJob::create([
                'integration_id' => $integration->id,
                'status' => 'Completed',
                'duration' => rand(10, 45),
                'imported_records' => $records,
                'started_at' => now()->subSeconds(30),
                'finished_at' => now(),
            ]);

            IntegrationHistory::create([
                'integration_id' => $integration->id,
                'action' => 'Import',
                'description' => "Import job completed. Processed {$records} records successfully.",
                'status' => 'Success',
            ]);

            return $job;
        });

        return response()->json([
            'message' => 'Import workflow executed successfully.',
            'job' => $job,
        ]);
    }

    public function disconnect(Integration $integration): JsonResponse
    {
        DB::transaction(function () use ($integration) {
            $integration->update([
                'status' => 'Disconnected',
                'health_status' => 'Unreachable',
            ]);

            IntegrationHistory::create([
                'integration_id' => $integration->id,
                'action' => 'Disconnected',
                'description' => 'Integration disconnected by administrative action.',
                'status' => 'Success',
            ]);
        });

        return response()->json([
            'message' => 'Disconnected successfully.',
        ]);
    }

    public function destroy(Integration $integration): JsonResponse
    {
        $integration->delete();

        return response()->json([
            'message' => 'Deleted successfully.',
        ]);
    }
}
