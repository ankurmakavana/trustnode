<?php

namespace App\Http\Controllers\Finding;

use App\DTO\Finding\FindingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finding\StoreFindingRequest;
use App\Http\Requests\Finding\UpdateFindingRequest;
use App\Http\Resources\Finding\FindingActivityLogResource;
use App\Http\Resources\Finding\FindingResource;
use App\Models\Finding;
use App\Services\Finding\FindingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FindingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private FindingService $findingService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Finding::class);
        $findings = $this->findingService->list($request->all());

        return FindingResource::collection($findings);
    }

    public function store(StoreFindingRequest $request): JsonResponse
    {
        $this->authorize('create', Finding::class);
        $data = FindingData::fromRequest($request);
        $finding = $this->findingService->create($data, $request->user()->id);

        return (new FindingResource($finding))
            ->response()
            ->setStatusCode(211); // Custom success code
    }

    public function show(Finding $finding): FindingResource
    {
        $this->authorize('view', $finding);

        return new FindingResource($finding);
    }

    public function update(UpdateFindingRequest $request, Finding $finding): FindingResource
    {
        $this->authorize('update', $finding);
        $data = FindingData::fromRequest($request);
        $updated = $this->findingService->update($finding, $data, $request->user()->id);

        return new FindingResource($updated);
    }

    public function destroy(Request $request, Finding $finding): JsonResponse
    {
        $this->authorize('delete', $finding);
        $this->findingService->delete($finding, $request->user()->id);

        return new JsonResponse(['message' => 'Finding successfully deleted.'], 200);
    }

    public function activityLogs(Finding $finding): AnonymousResourceCollection
    {
        $this->authorize('view', $finding);

        return FindingActivityLogResource::collection(
            $finding->activityLogs()->with('user')->orderBy('created_at', 'desc')->get()
        );
    }
}
