<?php

namespace App\Services\Asset;

use App\DTO\Asset\AssetData;
use App\Events\Asset\AssetCreated;
use App\Events\Asset\AssetDeleted;
use App\Events\Asset\AssetUpdated;
use App\Models\Asset;
use App\Models\AssetActivityLog;
use App\Models\AssetTag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetService
{
    /**
     * Get a paginated list of assets matching query/filters.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Asset::query()->with(['group', 'tags', 'creator', 'updater']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('value', 'like', $search)
                    ->orWhere('owner', 'like', $search);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['criticality'])) {
            $query->where('criticality', $filters['criticality']);
        }

        if (! empty($filters['asset_group_id'])) {
            $query->where('asset_group_id', $filters['asset_group_id']);
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('slug', $filters['tag']);
            });
        }

        // Sort params
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['name', 'type', 'risk_score', 'criticality', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new asset.
     */
    public function create(AssetData $data, User $creator): Asset
    {
        return DB::transaction(function () use ($data, $creator) {
            $asset = new Asset;
            $asset->fill([
                'name' => $data->name,
                'type' => $data->type,
                'value' => $data->value,
                'description' => $data->description,
                'criticality' => $data->criticality,
                'status' => $data->status,
                'risk_score' => $data->risk_score,
                'owner' => $data->owner,
                'notes' => $data->notes,
                'asset_group_id' => $data->asset_group_id,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);
            $asset->save();

            if (! empty($data->tags)) {
                $tagIds = $this->syncTags($data->tags);
                $asset->tags()->sync($tagIds);
            }

            // Write activity log
            $this->logActivity($asset, $creator, 'created', [
                'attributes' => $asset->toArray(),
            ]);

            // Dispatch Event
            event(new AssetCreated($asset));

            return $asset;
        });
    }

    /**
     * Update an existing asset.
     */
    public function update(Asset $asset, AssetData $data, User $updater): Asset
    {
        return DB::transaction(function () use ($asset, $data, $updater) {
            $oldAttributes = $asset->toArray();

            $asset->fill([
                'name' => $data->name,
                'type' => $data->type,
                'value' => $data->value,
                'description' => $data->description,
                'criticality' => $data->criticality,
                'status' => $data->status,
                'risk_score' => $data->risk_score,
                'owner' => $data->owner,
                'notes' => $data->notes,
                'asset_group_id' => $data->asset_group_id,
                'updated_by' => $updater->id,
            ]);

            if ($asset->isDirty()) {
                $asset->save();
            }

            if ($data->tags !== null) {
                $tagIds = $this->syncTags($data->tags);
                $asset->tags()->sync($tagIds);
            }

            $changes = [
                'old' => array_intersect_key($oldAttributes, $asset->getChanges()),
                'new' => $asset->getChanges(),
            ];

            if (! empty($changes['new'])) {
                // Log activity only on changes
                $this->logActivity($asset, $updater, 'updated', $changes);
                event(new AssetUpdated($asset, $changes));
            }

            return $asset;
        });
    }

    /**
     * Delete an asset.
     */
    public function delete(Asset $asset, User $user): bool
    {
        return DB::transaction(function () use ($asset, $user) {
            $this->logActivity($asset, $user, 'deleted');
            $deleted = $asset->delete();
            event(new AssetDeleted($asset));

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

            $tag = AssetTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName, 'color' => $this->getRandomHexColor()]
            );
            $ids[] = $tag->id;
        }

        return $ids;
    }

    /**
     * Log asset audit logs.
     */
    protected function logActivity(Asset $asset, User $user, string $action, ?array $properties = null): void
    {
        AssetActivityLog::create([
            'asset_id' => $asset->id,
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
