<?php

namespace App\Http\Controllers\Asset;

use App\DTO\Asset\AssetData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\Asset\AssetActivityLogResource;
use App\Http\Resources\Asset\AssetResource;
use App\Models\Asset;
use App\Services\Asset\AssetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected AssetService $assetService) {}

    /**
     * Display a listing of the assets matching filter criteria.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $filters = $request->only([
            'search', 'type', 'status', 'criticality', 'asset_group_id', 'tag', 'sort_by', 'sort_order',
        ]);

        $assets = $this->assetService->list($filters, (int) $request->input('per_page', 15));

        return AssetResource::collection($assets);
    }

    /**
     * Store a newly created asset in storage.
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        abort(403, 'Asset management is coming soon.');
        $this->authorize('create', Asset::class);

        $dto = AssetData::fromArray($request->validated());
        $asset = $this->assetService->create($dto, $request->user());

        return (new AssetResource($asset->load(['group', 'tags'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified asset.
     */
    public function show(Asset $asset): AssetResource
    {
        $this->authorize('viewAny', Asset::class);

        return new AssetResource($asset->load(['group', 'tags', 'creator', 'updater']));
    }

    /**
     * Update the specified asset in storage.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): AssetResource
    {
        $this->authorize('update', Asset::class);

        $dto = AssetData::fromArray($request->validated());
        $updatedAsset = $this->assetService->update($asset, $dto, $request->user());

        return new AssetResource($updatedAsset->load(['group', 'tags', 'creator', 'updater']));
    }

    /**
     * Remove the specified asset from storage.
     */
    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('delete', Asset::class);

        $this->assetService->delete($asset, $request->user());

        return response()->json(['message' => 'Asset deleted successfully.']);
    }

    /**
     * Display activity log history for specific asset.
     */
    public function activityLogs(Asset $asset): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $logs = $asset->activityLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return AssetActivityLogResource::collection($logs);
    }
}
