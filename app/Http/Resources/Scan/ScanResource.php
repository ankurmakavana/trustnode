<?php

namespace App\Http\Resources\Scan;

use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ScanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'engine' => $this->engine->value,
            'target' => $this->target,
            'repository' => $this->whenLoaded('repository', function () {
                return $this->repository ? [
                    'id' => $this->repository->id,
                    'name' => $this->repository->name,
                    'url' => $this->repository->repository_url,
                ] : null;
            }),
            'schedule' => $this->schedule,
            'status' => $this->status->value,
            'progress' => (int) $this->progress,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration' => $this->duration,
            'created_by' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null;
            }),
            'updated_by' => $this->whenLoaded('updater', function () {
                return $this->updater ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                ] : null;
            }),
            'findings_count' => $this->findings_count ?? $this->findings()->count(),
            'severity_counts' => $this->getSeverityCounts(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Compute severity breakdown from actual findings belonging to this scan.
     */
    protected function getSeverityCounts(): array
    {
        $counts = Finding::where('scan_id', $this->id)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $normalized = [];
        foreach ($counts as $key => $count) {
            $normalizedKey = strtolower(is_object($key) ? $key->value : $key);
            $normalized[$normalizedKey] = ($normalized[$normalizedKey] ?? 0) + $count;
        }

        return [
            'critical' => $normalized['critical'] ?? 0,
            'high'     => $normalized['high'] ?? 0,
            'medium'   => $normalized['medium'] ?? 0,
            'low'      => $normalized['low'] ?? 0,
            'info'     => $normalized['info'] ?? 0,
        ];
    }
}
