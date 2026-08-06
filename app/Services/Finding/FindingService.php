<?php

namespace App\Services\Finding;

use App\DTO\Finding\FindingData;
use App\Events\Finding\FindingCreated;
use App\Events\Finding\FindingDeleted;
use App\Events\Finding\FindingUpdated;
use App\Models\Finding;
use App\Models\FindingActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FindingService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Finding::query()->with(['asset', 'target', 'scan', 'analyst', 'creator']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('finding_id', 'like', "%{$search}%")
                    ->orWhere('cve', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        if (! empty($filters['target_id'])) {
            $query->where('target_id', $filters['target_id']);
        }

        if (! empty($filters['scan_id'])) {
            $query->where('scan_id', $filters['scan_id']);
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderBy('cvss_score', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(FindingData $data, int $userId): Finding
    {
        return DB::transaction(function () use ($data, $userId) {
            $finding = Finding::create(array_merge($data->toArray(), [
                'created_by' => $userId,
            ]));

            $this->logActivity($finding->id, 'created', [], $userId);
            event(new FindingCreated($finding));

            return $finding;
        });
    }

    public function update(Finding $finding, FindingData $data, int $userId): Finding
    {
        return DB::transaction(function () use ($finding, $data, $userId) {
            $original = $finding->only(['title', 'severity', 'status', 'cvss_score']);

            $finding->update(array_merge($data->toArray(), [
                'updated_by' => $userId,
            ]));

            $changes = [
                'old' => $original,
                'new' => $finding->only(['title', 'severity', 'status', 'cvss_score']),
            ];

            $this->logActivity($finding->id, 'updated', $changes, $userId);
            event(new FindingUpdated($finding));

            return $finding;
        });
    }

    public function delete(Finding $finding, int $userId): void
    {
        DB::transaction(function () use ($finding, $userId) {
            $this->logActivity($finding->id, 'deleted', [], $userId);
            event(new FindingDeleted($finding));
            $finding->delete();
        });
    }

    private function logActivity(int $findingId, string $action, array $properties = [], ?int $userId = null): void
    {
        FindingActivityLog::create([
            'finding_id' => $findingId,
            'action' => $action,
            'properties' => $properties,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
