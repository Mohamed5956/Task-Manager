<?php

namespace App\Actions\Tasks;

use App\Jobs\LogTaskActivity;
use App\Models\Task;
use App\Models\User;

class CreateTask
{
    public function execute(User $user, array $data): Task
    {
        $task = $user->tasks()->create([
            'tenant_id'   => $user->tenant_id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'todo',
            'due_date'    => $data['due_date'] ?? null,
        ]);

        LogTaskActivity::dispatch($task, $user, 'created');

        return $task->load('user');
    }
}