<?php

namespace App\Actions\Tasks;

use App\Jobs\LogTaskActivity;
use App\Models\Task;
use App\Models\User;

class UpdateTask
{
    public function execute(Task $task, User $user, array $data): Task
    {
        $task->update(array_filter($data, fn($v) => !is_null($v)));

        LogTaskActivity::dispatch($task->fresh(), $user, 'updated');

        return $task->refresh()->load('user');
    }
}