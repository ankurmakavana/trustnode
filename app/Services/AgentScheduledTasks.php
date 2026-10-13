<?php

namespace App\Services;

use App\Contracts\AgentQueueInterface;
use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\QueueFullException;
use App\Models\CodebaseProject;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enqueues scheduler-originated Agent tasks under the local administrator's identity.
 *
 * A task enqueued without a user context has no owner, and AgentWorker rejects it. The owner is
 * resolved at enqueue time: it must be an active administrator who belongs to a team. The tenant
 * context is set only for the enqueue call and cleared afterwards. If no such user exists,
 * nothing is enqueued.
 */
class AgentScheduledTasks
{
    public function __construct(private AgentQueueInterface $queue)
    {
    }

    public function localAdministratorId(): ?int
    {
        $adminRoleId = Role::where('slug', UserRole::ADMINISTRATOR->value)->value('id');

        if (! $adminRoleId) {
            return null;
        }

        return User::query()
            ->where('role_id', $adminRoleId)
            ->where('status', UserStatus::ACTIVE)
            ->whereHas('teams')
            ->orderBy('id')
            ->value('id');
    }

    public function enqueueAsLocalAdministrator(string $agentId, string $type, array $payload): ?string
    {
        if ($type === 'agent.observe_env' && ! AgentObserveEnvPolicy::enabled()) {
            return null;
        }

        $ownerId = $this->localAdministratorId();

        if ($ownerId === null) {
            Log::warning('Scheduled agent task not enqueued: no active administrator with a team exists.', [
                'agent_id' => $agentId,
                'type' => $type,
            ]);

            return null;
        }

        TenantContext::setUserId($ownerId);

        try {
            return $this->queue->enqueue($agentId, $type, $payload);
        } finally {
            TenantContext::clear();
        }
    }

    /**
     * Enqueue one project-scoped codebase scan for each eligible project: registered, active,
     * continuous scanning enabled, and in an organization the owner belongs to. Returns the ids of
     * the projects that were queued. Fails closed (returns an empty list) if the owner or their
     * organizations cannot be resolved. The whole scan mount is never scanned.
     *
     * @return int[]
     */
    public function enqueueProjectScans(string $agentId): array
    {
        $ownerId = $this->localAdministratorId();

        if ($ownerId === null) {
            Log::warning('Continuous project scans skipped: no active administrator with a team exists.', ['agent_id' => $agentId]);

            return [];
        }

        $organizationIds = User::find($ownerId)?->organizations()->pluck('id')->all() ?? [];

        if ($organizationIds === []) {
            Log::warning('Continuous project scans skipped: the administrator belongs to no organization.', ['agent_id' => $agentId]);

            return [];
        }

        // Only the tenant scope is removed, because the scheduler has no request user. Soft-deleted
        // projects stay excluded, and the organization filter below replaces the tenant check.
        $projects = CodebaseProject::withoutGlobalScope(TenantScope::class)
            ->where('status', CodebaseProjectStatus::ACTIVE)
            ->where('continuous_scan_enabled', true)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('id')
            ->get();

        $queued = [];

        foreach ($projects as $project) {
            if ($this->hasPendingProjectScan($agentId, $project->id)) {
                continue;
            }

            try {
                $taskId = $this->enqueueAsLocalAdministrator($agentId, 'agent.capability.execute', [
                    'capability_id' => 'agent:scan_codebase',
                    'arguments' => ['project_id' => $project->id],
                ]);
            } catch (QueueFullException) {
                break;
            }

            if ($taskId !== null) {
                $queued[] = $project->id;
            }
        }

        return $queued;
    }

    private function hasPendingProjectScan(string $agentId, int $projectId): bool
    {
        return DB::table('agent_tasks')
            ->where('agent_id', $agentId)
            ->where('type', 'agent.capability.execute')
            ->whereIn('status', [AgentQueue::STATUS_PENDING, AgentQueue::STATUS_PROCESSING])
            ->pluck('payload')
            ->contains(function ($payload) use ($projectId) {
                $decoded = json_decode((string) $payload, true);

                return ($decoded['arguments']['project_id'] ?? null) === $projectId;
            });
    }
}
