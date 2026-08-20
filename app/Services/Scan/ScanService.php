<?php

namespace App\Services\Scan;

use App\DTO\Scan\ScanData;
use App\Events\Scan\ScanCreated;
use App\Events\Scan\ScanDeleted;
use App\Events\Scan\ScanUpdated;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScanService
{
    /**
     * List and paginate scans with dynamic search filters.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Scan::query()->with(['creator', 'updater', 'repository'])->withCount('findings');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('target', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['engine'])) {
            $query->where('engine', $filters['engine']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSort = ['name', 'type', 'engine', 'status', 'created_at', 'progress'];
        if (in_array($sortBy, $allowedSort, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new scan.
     */
    public function create(ScanData $dto, User $creator): Scan
    {
        return DB::transaction(function () use ($dto, $creator) {
            $data = $dto->toArray();
            $data['created_by'] = $creator->id;
            $data['updated_by'] = $creator->id;

            if (!empty($data['repository_id'])) {
                // Lock repository if possible to serialize requests
                \App\Models\Repository::where('id', $data['repository_id'])->lockForUpdate()->first();
                
                $activeScan = Scan::where('repository_id', $data['repository_id'])
                    ->whereIn('status', [\App\Enums\Scan\ScanStatus::QUEUED, \App\Enums\Scan\ScanStatus::RUNNING])
                    ->lockForUpdate()
                    ->first();
                    
                if ($activeScan) {
                    throw new \InvalidArgumentException('A scan is already active for this repository.');
                }
            }

            $scan = Scan::create($data);

            // Log activity
            $scan->activityLogs()->create([
                'action' => 'created',
                'properties' => ['new' => $scan->toArray()],
                'user_id' => $creator->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            event(new ScanCreated($scan));

            return $scan;
        });
    }

    /**
     * Update an existing scan.
     */
    public function update(Scan $scan, ScanData $dto, User $updater): Scan
    {
        return DB::transaction(function () use ($scan, $dto, $updater) {
            $oldData = $scan->toArray();
            $data = $dto->toArray();
            $data['updated_by'] = $updater->id;

            $scan->update($data);

            // Log activity
            $scan->activityLogs()->create([
                'action' => 'updated',
                'properties' => [
                    'old' => $oldData,
                    'new' => $scan->toArray(),
                ],
                'user_id' => $updater->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            event(new ScanUpdated($scan));

            return $scan;
        });
    }

    /**
     * Delete a scan scope.
     */
    public function delete(Scan $scan, User $user): bool
    {
        return DB::transaction(function () use ($scan, $user) {
            // Log activity
            $scan->activityLogs()->create([
                'action' => 'deleted',
                'properties' => ['deleted' => $scan->toArray()],
                'user_id' => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            event(new ScanDeleted($scan));

            return (bool) $scan->delete();
        });
    }
}
