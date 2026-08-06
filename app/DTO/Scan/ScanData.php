<?php

namespace App\DTO\Scan;

class ScanData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $type,
        public string $engine,
        public string $target,
        public ?string $schedule,
        public string $status,
        public int $progress,
        public ?string $startedAt = null,
        public ?string $completedAt = null,
        public ?int $duration = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            type: $data['type'],
            engine: $data['engine'],
            target: $data['target'],
            schedule: $data['schedule'] ?? null,
            status: $data['status'] ?? 'queued',
            progress: $data['progress'] ?? 0,
            startedAt: $data['started_at'] ?? null,
            completedAt: $data['completed_at'] ?? null,
            duration: $data['duration'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'engine' => $this->engine,
            'target' => $this->target,
            'schedule' => $this->schedule,
            'status' => $this->status,
            'progress' => $this->progress,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'duration' => $this->duration,
        ];
    }
}
