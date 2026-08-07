<?php

namespace App\Services\Import;

use App\Jobs\ValidateImportJob as QueueValidateJob;
use App\Models\ImportFile;
use App\Models\ImportJob;
use App\Services\Import\Adapters\BurpAdapter;
use App\Services\Import\Adapters\GreenboneAdapter;
use App\Services\Import\Adapters\ImportAdapterInterface;
use App\Services\Import\Adapters\NessusAdapter;
use App\Services\Import\Adapters\NmapAdapter;
use App\Services\Import\Adapters\QualysAdapter;
use App\Services\Import\Adapters\Rapid7Adapter;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImportService
{
    protected $validator;

    protected $previewer;

    public function __construct(ValidationService $validator, PreviewService $previewer)
    {
        $this->validator = $validator;
        $this->previewer = $previewer;
    }

    /**
     * Resolve the parsing adapter based on scanner code.
     */
    public function getAdapter(string $scanner): ImportAdapterInterface
    {
        return match ($scanner) {
            'nmap' => new NmapAdapter,
            'nessus' => new NessusAdapter,
            'greenbone' => new GreenboneAdapter,
            'burp' => new BurpAdapter,
            'qualys' => new QualysAdapter,
            'rapid7' => new Rapid7Adapter,
            default => throw new Exception("Unsupported scanner adapter type: {$scanner}"),
        };
    }

    /**
     * Handle manual file upload and create a pending import job.
     */
    public function createFromFile(UploadedFile $file, string $scanner, ?int $integrationId = null): ImportJob
    {
        $job = ImportJob::create([
            'integration_id' => $integrationId,
            'status' => 'pending',
            'progress' => 0,
            'source_type' => 'file',
            'created_by' => auth()->id() ?? 1,
        ]);

        $path = $file->storeAs(
            'imports',
            (string) Str::uuid().'.'.$file->getClientOriginalExtension()
        );

        ImportFile::create([
            'import_job_id' => $job->id,
            'filename' => $file->getClientOriginalName(),
            'filepath' => $path,
            'filesize' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        $job->logs()->create([
            'level' => 'info',
            'message' => "Import job created for scanner type: {$scanner}.",
        ]);

        // Dispatch background queue validation
        QueueValidateJob::dispatch($job, $scanner);

        return $job;
    }
}
