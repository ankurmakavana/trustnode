<?php

namespace App\Http\Controllers\Risk;

use App\Http\Controllers\Controller;
use App\Models\Risk;
use App\Services\Risk\RiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    public function __construct(private RiskService $riskService) {}

    public function index(Request $request): JsonResponse
    {
        $risks = $this->riskService->list($request->all());

        return response()->json($risks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'business_impact' => 'nullable|string',
            'technical_impact' => 'nullable|string',
            'likelihood' => 'required|string|in:Rare,Unlikely,Possible,Likely,Almost Certain',
            'impact' => 'required|string|in:Negligible,Minor,Moderate,Major,Catastrophic',
            'status' => 'nullable|string|in:Open,Mitigating,Accepted,Resolved,Closed',
            'owner_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'findings' => 'nullable|array',
            'findings.*' => 'exists:findings,id',
        ]);

        $risk = $this->riskService->create($validated, $request->user()->id);

        return response()->json([
            'message' => 'Risk registered successfully.',
            'data' => $risk,
        ], 201);
    }

    public function show(Risk $risk): JsonResponse
    {
        $risk->load(['owner', 'findings', 'treatments.creator', 'history.user']);

        return response()->json(['data' => $risk]);
    }

    public function update(Request $request, Risk $risk): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'business_impact' => 'nullable|string',
            'technical_impact' => 'nullable|string',
            'likelihood' => 'required|string|in:Rare,Unlikely,Possible,Likely,Almost Certain',
            'impact' => 'required|string|in:Negligible,Minor,Moderate,Major,Catastrophic',
            'status' => 'nullable|string|in:Open,Mitigating,Accepted,Resolved,Closed',
            'owner_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'findings' => 'nullable|array',
            'findings.*' => 'exists:findings,id',
        ]);

        $updated = $this->riskService->update($risk, $validated, $request->user()->id);

        return response()->json([
            'message' => 'Risk profile updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Risk $risk): JsonResponse
    {
        $this->riskService->destroy($risk, $request->user()->id);

        return response()->json([
            'message' => 'Risk deleted successfully.',
        ]);
    }

    public function addTreatment(Request $request, Risk $risk): JsonResponse
    {
        $validated = $request->validate([
            'treatment_type' => 'required|string|in:Mitigate,Transfer,Avoid,Accept',
            'description' => 'required|string',
            'target_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $treatment = $this->riskService->addTreatment($risk, $validated, $request->user()->id);

        return response()->json([
            'message' => 'Treatment plan added.',
            'data' => $treatment,
        ], 201);
    }

    public function accept(Request $request, Risk $risk): JsonResponse
    {
        $accepted = $this->riskService->accept($risk, $request->user()->id);

        return response()->json([
            'message' => 'Risk formal acceptance authorized.',
            'data' => $accepted,
        ]);
    }
}
