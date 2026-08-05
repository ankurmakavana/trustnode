<?php

namespace App\Services\Target;

use App\DTO\Target\TargetData;
use App\Events\Target\TargetCreated;
use App\Events\Target\TargetDeleted;
use App\Events\Target\TargetUpdated;
use App\Models\Target;
use App\Models\TargetActivityLog;
use App\Models\TargetTag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TargetService
{
    /**
     * Get a paginated list of targets matching query/filters.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Target::query()->with(['tags', 'creator', 'updater']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('value', 'like', $search);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['environment'])) {
            $query->where('environment', $filters['environment']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['criticality'])) {
            $query->where('criticality', $filters['criticality']);
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('slug', $filters['tag']);
            });
        }

        // Sort params
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['name', 'type', 'environment', 'criticality', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new target.
     */
    public function create(TargetData $data, User $creator): Target
    {
        return DB::transaction(function () use ($data, $creator) {
            $target = new Target;
            $target->fill([
                'name' => $data->name,
                'type' => $data->type,
                'value' => $data->value,
                'environment' => $data->environment,
                'description' => $data->description,
                'criticality' => $data->criticality,
                'status' => $data->status,
                'scope_notes' => $data->scope_notes,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);
            $target->save();

            if (! empty($data->tags)) {
                $tagIds = $this->syncTags($data->tags);
                $target->tags()->sync($tagIds);
            }

            // Write activity log
            $this->logActivity($target, $creator, 'created', [
                'attributes' => $target->toArray(),
            ]);

            // Dispatch Event
            event(new TargetCreated($target));

            return $target;
        });
    }

    /**
     * Update an existing target.
     */
    public function update(Target $target, TargetData $data, User $updater): Target
    {
        return DB::transaction(function () use ($target, $data, $updater) {
            $oldAttributes = $target->toArray();

            $target->fill([
                'name' => $data->name,
                'type' => $data->type,
                'value' => $data->value,
                'environment' => $data->environment,
                'description' => $data->description,
                'criticality' => $data->criticality,
                'status' => $data->status,
                'scope_notes' => $data->scope_notes,
                'updated_by' => $updater->id,
            ]);

            if ($target->isDirty()) {
                $target->save();
            }

            if ($data->tags !== null) {
                $tagIds = $this->syncTags($data->tags);
                $target->tags()->sync($tagIds);
            }

            $changes = [
                'old' => array_intersect_key($oldAttributes, $target->getChanges()),
                'new' => $target->getChanges(),
            ];

            if (! empty($changes['new'])) {
                // Log activity only on changes
                $this->logActivity($target, $updater, 'updated', $changes);
                event(new TargetUpdated($target, $changes));
            }

            return $target;
        });
    }

    /**
     * Delete a target.
     */
    public function delete(Target $target, User $user): bool
    {
        return DB::transaction(function () use ($target, $user) {
            $this->logActivity($target, $user, 'deleted');
            $deleted = $target->delete();
            event(new TargetDeleted($target));

            return $deleted;
        });
    }

    /**
     * Sync string tags to database IDs, creating missing tags on the fly.
     */
    protected function syncTags(array $tags): array
    {
        $ids = [];
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = TargetTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName, 'color' => $this->getRandomHexColor()]
            );
            $ids[] = $tag->id;
        }

        return $ids;
    }

    /**
     * Log target audit logs.
     */
    protected function logActivity(Target $target, User $user, string $action, ?array $properties = null): void
    {
        TargetActivityLog::create([
            'target_id' => $target->id,
            'user_id' => $user->id,
            'action' => $action,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Helper to get soft neutral/pastel colors for tags.
     */
    protected function getRandomHexColor(): string
    {
        $colors = [
            '#f1f5f9', // slate
            '#fee2e2', // red
            '#ffedd5', // orange
            '#fef9c3', // yellow
            '#dcfce7', // green
            '#e0e7ff', // indigo
            '#f3e8ff', // purple
            '#fae8ff', // pink
        ];

        return $colors[array_rand($colors)];
    }
}
