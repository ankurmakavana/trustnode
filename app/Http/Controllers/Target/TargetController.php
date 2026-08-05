<?php

namespace App\Http\Controllers\Target;

use App\DTO\Target\TargetData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Target\StoreTargetRequest;
use App\Http\Requests\Target\UpdateTargetRequest;
use App\Http\Resources\Target\TargetActivityLogResource;
use App\Http\Resources\Target\TargetResource;
use App\Models\Target;
use App\Services\Target\TargetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TargetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected TargetService $targetService) {}

    /**
     * Display a listing of targets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Target::class);

        $filters = $request->only([
            'search', 'type', 'environment', 'status', 'criticality', 'tag', 'sort_by', 'sort_order',
        ]);

        $targets = $this->targetService->list($filters, (int) $request->input('per_page', 15));

        return TargetResource::collection($targets);
    }

    /**
     * Store a newly created target.
     */
    public function store(StoreTargetRequest $request): JsonResponse
    {
        $this->authorize('create', Target::class);

        $dto = TargetData::fromArray($request->validated());
        $target = $this->targetService->create($dto, $request->user());

        return (new TargetResource($target->load(['tags'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified target.
     */
    public function show(Target $target): TargetResource
    {
        $this->authorize('viewAny', Target::class);

        return new TargetResource($target->load(['tags', 'creator', 'updater']));
    }

    /**
     * Update the specified target.
     */
    public function update(UpdateTargetRequest $request, Target $target): TargetResource
    {
        $this->authorize('update', Target::class);

        $dto = TargetData::fromArray($request->validated());
        $updatedTarget = $this->targetService->update($target, $dto, $request->user());

        return new TargetResource($updatedTarget->load(['tags', 'creator', 'updater']));
    }

    /**
     * Remove the specified target.
     */
    public function destroy(Request $request, Target $target): JsonResponse
    {
        $this->authorize('delete', Target::class);

        $this->targetService->delete($target, $request->user());

        return response()->json(['message' => 'Target deleted successfully.']);
    }

    /**
     * Display activity log history for specific target.
     */
    public function activityLogs(Target $target): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Target::class);

        $logs = $target->activityLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return TargetActivityLogResource::collection($logs);
    }
}
