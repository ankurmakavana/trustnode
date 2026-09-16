<?php

namespace App\Services;

use App\Contracts\AgentQueueInterface;
use App\Exceptions\QueueFullException;
use App\Exceptions\InvalidPayloadException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use LogicException;

class AgentQueue implements AgentQueueInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';

    protected $table = 'agent_tasks';
    protected $maxSize;
    protected $maxPayloadSize = 65535; // 64KB JSON limit

    public function __construct()
    {
        $this->maxSize = config('agent.queue.max_size', 1000);
    }

    public function enqueue(string $agentId, string $type, array $payload): string
    {
        if ($this->isFull($agentId)) {
            throw new QueueFullException("Agent queue for [{$agentId}] has reached its capacity of {$this->maxSize}.");
        }

        $this->validatePayload($payload);

        $id = (string) Str::orderedUuid();

        DB::table($this->table)->insert([
            'id' => $id,
            'agent_id' => $agentId,
            'type' => $type,
            'payload' => json_encode($payload),
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function dequeue(string $agentId): ?array
    {
        return DB::transaction(function () use ($agentId) {
            $task = DB::table($this->table)
                ->where('agent_id', $agentId)
                ->where('status', self::STATUS_PENDING)
                ->where(function ($query) {
                    $query->whereNull('available_at')
                          ->orWhere('available_at', '<=', now());
                })
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return null;
            }

            DB::table($this->table)
                ->where('id', $task->id)
                ->update([
                    'status' => self::STATUS_PROCESSING,
                    'updated_at' => now(),
                ]);

            $taskArray = (array) $task;
            $taskArray['payload'] = json_decode($taskArray['payload'], true);
            $taskArray['status'] = self::STATUS_PROCESSING;
            
            return $taskArray;
        });
    }

    public function acknowledge(string $taskId): void
    {
        $affected = DB::table($this->table)
            ->where('id', $taskId)
            ->where('status', self::STATUS_PROCESSING)
            ->update([
                'status' => self::STATUS_COMPLETED,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new LogicException("Cannot acknowledge task [{$taskId}]. It is either missing or not processing.");
        }
    }

    public function fail(string $taskId, ?Throwable $exception = null): void
    {
        $affected = DB::table($this->table)
            ->where('id', $taskId)
            ->where('status', self::STATUS_PROCESSING)
            ->update([
                'status' => self::STATUS_FAILED,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new LogicException("Cannot fail task [{$taskId}]. It is either missing or not processing.");
        }
    }

    public function size(string $agentId): int
    {
        return DB::table($this->table)
            ->where('agent_id', $agentId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->count();
    }

    public function isFull(string $agentId): bool
    {
        return $this->size($agentId) >= $this->maxSize;
    }

    protected function validatePayload(array $payload): void
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new InvalidPayloadException('Payload must be JSON serializable.');
        }

        if (strlen($json) > $this->maxPayloadSize) {
            throw new InvalidPayloadException('Payload size exceeds ' . $this->maxPayloadSize . ' bytes.');
        }

        // Deep key search for explicitly forbidden concepts
        $this->checkForbiddenKeys($payload);
    }

    protected function checkForbiddenKeys(array $payload): void
    {
        $forbidden = ['command', 'exec', 'eval', 'shell', 'password', 'secret', 'key', 'token'];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($forbidden as $forbid) {
                    // if key exactly matches forbidden or contains it in a dangerous way, reject.
                    // Keep it simple as requested: reject if exact match
                    if ($lowerKey === $forbid) {
                        throw new InvalidPayloadException("Payload contains forbidden sensitive/executable key: {$key}");
                    }
                }
            }
            if (is_array($value)) {
                $this->checkForbiddenKeys($value);
            }
        }
    }
}
