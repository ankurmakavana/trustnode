<?php

namespace App\Services\Risk;

use App\Models\Risk;
use App\Models\RiskHistory;
use App\Models\RiskTreatment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RiskService
{
    private array $likelihoodMap = [
        'Rare' => 1,
        'Unlikely' => 2,
        'Possible' => 3,
        'Likely' => 4,
        'Almost Certain' => 5,
    ];

    private array $impactMap = [
        'Negligible' => 1,
        'Minor' => 2,
        'Moderate' => 3,
        'Major' => 4,
        'Catastrophic' => 5,
    ];

    public function calculateScoreAndLevel(string $likelihood, string $impact): array
    {
        $lScore = $this->likelihoodMap[$likelihood] ?? 3;
        $iScore = $this->impactMap[$impact] ?? 3;
        $score = $lScore * $iScore;

        if ($score >= 16) {
            $level = 'Critical';
        } elseif ($score >= 10) {
            $level = 'High';
        } elseif ($score >= 5) {
            $level = 'Medium';
        } else {
            $level = 'Low';
        }

        return [
            'score' => $score,
            'level' => $level,
        ];
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Risk::query()->with(['owner', 'findings', 'treatments', 'history.user']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('risk_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (isset($filters['likelihood'])) {
            $query->where('likelihood', $filters['likelihood']);
        }

        if (isset($filters['impact'])) {
            $query->where('impact', $filters['impact']);
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderBy('risk_score', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data, int $userId): Risk
    {
        return DB::transaction(function () use ($data, $userId) {
            $scores = $this->calculateScoreAndLevel($data['likelihood'], $data['impact']);

            $risk = Risk::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'business_impact' => $data['business_impact'] ?? null,
                'technical_impact' => $data['technical_impact'] ?? null,
                'likelihood' => $data['likelihood'],
                'impact' => $data['impact'],
                'risk_score' => $scores['score'],
                'risk_level' => $scores['level'],
                'status' => $data['status'] ?? 'Open',
                'owner_id' => $data['owner_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'review_date' => $data['review_date'] ?? null,
                'created_by' => $userId,
            ]);

            if (! empty($data['findings'])) {
                $risk->findings()->sync($data['findings']);
            }

            RiskHistory::create([
                'risk_id' => $risk->id,
                'action' => 'Created',
                'description' => 'Created Risk register item: '.$risk->risk_id,
                'user_id' => $userId,
            ]);

            return $risk;
        });
    }

    public function update(Risk $risk, array $data, int $userId): Risk
    {
        return DB::transaction(function () use ($risk, $data, $userId) {
            $scores = $this->calculateScoreAndLevel($data['likelihood'], $data['impact']);

            $risk->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'business_impact' => $data['business_impact'] ?? null,
                'technical_impact' => $data['technical_impact'] ?? null,
                'likelihood' => $data['likelihood'],
                'impact' => $data['impact'],
                'risk_score' => $scores['score'],
                'risk_level' => $scores['level'],
                'status' => $data['status'] ?? $risk->status,
                'owner_id' => $data['owner_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'review_date' => $data['review_date'] ?? null,
                'updated_by' => $userId,
            ]);

            if (isset($data['findings'])) {
                $risk->findings()->sync($data['findings']);
            }

            RiskHistory::create([
                'risk_id' => $risk->id,
                'action' => 'Updated',
                'description' => 'Modified Risk registers properties',
                'user_id' => $userId,
            ]);

            return $risk;
        });
    }

    public function addTreatment(Risk $risk, array $data, int $userId): RiskTreatment
    {
        return DB::transaction(function () use ($risk, $data, $userId) {
            $treatment = RiskTreatment::create([
                'risk_id' => $risk->id,
                'treatment_type' => $data['treatment_type'],
                'description' => $data['description'],
                'target_date' => $data['target_date'] ?? null,
                'status' => $data['status'] ?? 'Open',
                'created_by' => $userId,
            ]);

            RiskHistory::create([
                'risk_id' => $risk->id,
                'action' => 'Treatment Added',
                'description' => 'Added treatment strategy: '.$data['treatment_type'],
                'user_id' => $userId,
            ]);

            // Automatically transition status to 'Mitigating' if not accepted or closed
            if ($risk->status === 'Open') {
                $risk->update(['status' => 'Mitigating']);
            }

            return $treatment;
        });
    }

    public function accept(Risk $risk, int $userId): Risk
    {
        return DB::transaction(function () use ($risk, $userId) {
            $risk->update([
                'status' => 'Accepted',
                'accepted' => true,
                'accepted_by' => $userId,
                'accepted_at' => now(),
            ]);

            RiskHistory::create([
                'risk_id' => $risk->id,
                'action' => 'Accepted',
                'description' => 'Risk formal acceptance authorized by workspace managers.',
                'user_id' => $userId,
            ]);

            return $risk;
        });
    }

    public function destroy(Risk $risk, int $userId): void
    {
        DB::transaction(function () use ($risk, $userId) {
            RiskHistory::create([
                'risk_id' => $risk->id,
                'action' => 'Deleted',
                'description' => 'Risk deleted.',
                'user_id' => $userId,
            ]);
            $risk->delete();
        });
    }
}
