<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseImportJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $content;

    public function __construct(ImportJob $jobModel, string $scanner, string $content)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->content = $content;
    }

    public function handle(ImportService $importService)
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Parsing phase started.',
        ]);

        try {
            $adapter = $importService->getAdapter($this->scanner);
            $parsed = $adapter->parse($this->content);

            $this->jobModel->logs()->create([
                'level' => 'info',
                'message' => 'Parsing completed. Assets extracted: '.count($parsed['assets']).', Findings extracted: '.count($parsed['findings']),
            ]);

            $this->jobModel->update([
                'status' => 'previewing',
                'progress' => 45,
            ]);

            NormalizeImportJob::dispatch($this->jobModel, $this->scanner, $parsed);
        } catch (\Exception $e) {
            $this->jobModel->update([
                'status' => 'failed',
                'progress' => 100,
                'error_message' => 'Parsing failed: '.$e->getMessage(),
            ]);
            $this->jobModel->logs()->create([
                'level' => 'error',
                'message' => 'Parsing failed: '.$e->getMessage(),
            ]);
        }
    }

    public function getTenantId(): ?int
    {
        return $this->jobModel->created_by;
    }
}
