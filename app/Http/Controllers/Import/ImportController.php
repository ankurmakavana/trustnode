<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\ImportJob;
use App\Models\Integration;
use App\Services\Import\ImportService;
use App\Services\Import\PreviewService;
use App\Services\Import\ValidationService;
use Exception;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    protected $importService;

    protected $validationService;

    protected $previewService;

    public function __construct(
        ImportService $importService,
        ValidationService $validationService,
        PreviewService $previewService
    ) {
        $this->importService = $importService;
        $this->validationService = $validationService;
        $this->previewService = $previewService;
    }

    /**
     * List recent import jobs.
     */
    public function index()
    {
        $jobs = ImportJob::with(['integration', 'file', 'history'])
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * List completed import histories.
     */
    public function history()
    {
        $history = ImportHistory::with('importJob.integration')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Show import job details, progress and logs.
     */
    public function show($uuid)
    {
        $job = ImportJob::where('uuid', $uuid)
            ->with(['integration', 'file', 'history', 'logs'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    /**
     * Upload scan file and start background validation/processing.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'scanner' => 'required|string|in:nmap,nessus,greenbone,burp,qualys,rapid7',
            'integration_id' => 'nullable|exists:integrations,id',
        ]);

        try {
            $file = $request->file('file');
            $scanner = $request->input('scanner');
            $integrationId = $request->input('integration_id');

            $job = $this->importService->createFromFile($file, $scanner, $integrationId);

            return response()->json([
                'success' => true,
                'message' => 'Import job started successfully running in the background.',
                'data' => $job,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start import: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview import file results before committing.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'scanner' => 'required|string|in:nmap,nessus,greenbone,burp,qualys,rapid7',
        ]);

        try {
            $file = $request->file('file');
            $scanner = $request->input('scanner');

            $content = file_get_contents($file->getRealPath());
            $adapter = $this->importService->getAdapter($scanner);
            $parsed = $adapter->parse($content);
            $preview = $this->previewService->generatePreview($parsed);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Trigger scanner connection sync (API import).
     */
    public function triggerConnectionImport(Integration $integration, Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Integration API sync is not yet implemented for real external scanners in this version. Please use file import.',
        ], 501);
    }

    /**
     * Delete/Cancel an import job record.
     */
    public function destroy($uuid)
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();

        if ($job->status === 'pending' || $job->status === 'validating' || $job->status === 'parsing') {
            $job->update([
                'status' => 'failed',
                'error_message' => 'Job was manually cancelled by user.',
            ]);
            $job->logs()->create([
                'level' => 'warning',
                'message' => 'Import job was cancelled by user.',
            ]);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Import job deleted successfully.',
        ]);
    }
}
