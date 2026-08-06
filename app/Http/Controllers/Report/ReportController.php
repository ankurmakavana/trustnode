<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Report::query()->with('creator');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('report_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('owner_id')) {
            $query->where('created_by', $request->input('owner_id'));
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:Executive Summary,Technical Assessment,Risk Report,Compliance Report,Asset Coverage,Scan Coverage',
            'options' => 'nullable|array',
        ]);

        $report = DB::transaction(function () use ($validated, $request) {
            $report = Report::create([
                'title' => $validated['title'],
                'type' => $validated['type'],
                'options' => $validated['options'] ?? [],
                'created_by' => $request->user()->id,
                'status' => 'Generated',
            ]);

            ReportHistory::create([
                'report_id' => $report->id,
                'action' => 'Generated',
                'description' => "Initial report generation of type {$report->type}.",
                'user_id' => $request->user()->id,
            ]);

            return $report;
        });

        return response()->json([
            'message' => 'Report generated successfully.',
            'data' => $report,
        ], 201);
    }

    public function show(Report $report): JsonResponse
    {
        $report->load(['creator', 'histories.user']);

        // Log a viewed history log
        ReportHistory::create([
            'report_id' => $report->id,
            'action' => 'Viewed',
            'description' => 'Report viewed by analyst.',
            'user_id' => auth()->id(),
        ]);

        return response()->json(['data' => $report]);
    }

    public function duplicate(Request $request, Report $report): JsonResponse
    {
        $newReport = DB::transaction(function () use ($report, $request) {
            $copy = Report::create([
                'title' => $report->title.' (Copy)',
                'type' => $report->type,
                'options' => $report->options,
                'created_by' => $request->user()->id,
                'status' => 'Generated',
            ]);

            ReportHistory::create([
                'report_id' => $copy->id,
                'action' => 'Generated',
                'description' => "Duplicated from parent report {$report->report_id}.",
                'user_id' => $request->user()->id,
            ]);

            return $copy;
        });

        return response()->json([
            'message' => 'Report duplicated successfully.',
            'data' => $newReport,
        ], 201);
    }

    public function archive(Request $request, Report $report): JsonResponse
    {
        DB::transaction(function () use ($report, $request) {
            $report->update(['status' => 'Archived']);

            ReportHistory::create([
                'report_id' => $report->id,
                'action' => 'Archived',
                'description' => 'Report transitioned to archived status.',
                'user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Report archived successfully.',
            'data' => $report,
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully.',
        ]);
    }
}
