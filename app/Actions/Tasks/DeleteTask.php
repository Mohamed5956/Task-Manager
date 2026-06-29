<?php

namespace App\Actions\Tasks;

use App\Jobs\LogTaskActivity;
use App\Models\Task;
use App\Models\User;

class DeleteTask
{
    public function execute(Task $task, User $user): void
    {
        LogTaskActivity::dispatch($task, $user, 'deleted');
        $task->delete();
    }
}