<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class LogTaskActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;
    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Task   $task,
        public readonly User   $user,
        public readonly string $action,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Task activity', [
            'action'    => $this->action,
            'task_id'   => $this->task->id,
            'title'     => $this->task->title,
            'user_id'   => $this->user->id,
            'tenant_id' => $this->task->tenant_id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('LogTaskActivity failed', ['task_id' => $this->task->id, 'error' => $e->getMessage()]);
    }
}
