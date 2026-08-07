<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ValidateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    public function __construct(ImportJob $jobModel, string $scanner)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
    }

    public function handle(ValidationService $validator)
    {
        $this->jobModel->update([
            'status' => 'validating',
            'progress' => 10,
        ]);
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Validation phase started.',
        ]);

        $fileModel = $this->jobModel->file;
        if (! $fileModel) {
            $this->failJob('Uploaded file missing from job state.');

            return;
        }

        if (! Storage::exists($fileModel->filepath)) {
            $this->failJob("File path not found: {$fileModel->filepath}");

            return;
        }

        $content = Storage::get($fileModel->filepath);
        $res = $validator->validate($content, $this->scanner);

        if (! $res['valid']) {
            $errorList = implode(', ', $res['errors']);
            $this->failJob("Validation failed: {$errorList}");

            return;
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => "Validation passed successfully. Version identified: {$res['version']}.",
        ]);

        // Proceed to parsing
        $this->jobModel->update([
            'status' => 'parsing',
            'progress' => 25,
        ]);

        ParseImportJob::dispatch($this->jobModel, $this->scanner, $content);
    }

    protected function failJob(string $reason)
    {
        $this->jobModel->update([
            'status' => 'failed',
            'progress' => 100,
            'error_message' => $reason,
        ]);
        $this->jobModel->logs()->create([
            'level' => 'error',
            'message' => $reason,
        ]);

        // Write import history to fail
        $this->jobModel->history()->create([
            'scanner' => $this->scanner,
            'status' => 'failed',
        ]);
    }
}
