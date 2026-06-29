<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\UpdateTask;
use App\Filters\TaskFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    use ApiResponse,AuthorizesRequests;

    /**
     * @throws \JsonException
     */
    public function index(Request $request, TaskFilter $filter): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $cacheKey = 'list:' . md5(json_encode($request->query(), JSON_THROW_ON_ERROR));
        $tasks = Cache::tags([
            "tenant:{$tenantId}",
            'tasks',
        ])->remember(
            $cacheKey,
            now()->addHour(),
            fn () => Task::filter($filter)
                ->with('user')
                ->latest()
                ->paginate(15)
        );

        return $this->success(
            TaskResource::collection($tasks),
            'Tasks retrieved successfully.'
        );
    }

    public function store(StoreTaskRequest $request, CreateTask $action): JsonResponse
    {
        $task = $action->execute($request->user(), $request->validated());

        $this->flushTaskCache($request->user()->tenant_id);

        return $this->success(
            new TaskResource($task),
            'Task created successfully.',
            201
        );
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return $this->success(
            new TaskResource($task->load('user')),
            'Task retrieved successfully.'
        );
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $action): JsonResponse
    {
        $task = $action->execute($task, $request->user(), $request->validated());

        $this->flushTaskCache($request->user()->tenant_id);

        return $this->success(
            new TaskResource($task),
            'Task updated successfully.'
        );
    }

    public function destroy(Request $request, Task $task, DeleteTask $action): JsonResponse
    {
        $this->authorize('delete', $task);

        $action->execute($task, $request->user());

        $this->flushTaskCache($request->user()->tenant_id);

        return $this->success(
            null,
            'Task deleted successfully.'
        );
    }

    private function flushTaskCache(int $tenantId): void
    {
        Cache::tags([
            "tenant:{$tenantId}",
            'tasks',
        ])->flush();
    }
}
