<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\TaskStatus;


class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'due_date'   => $this->due_date?->toDateString(),
            'is_overdue' => $this->due_date?->isPast() && $this->status !== TaskStatus::Done,
            'assignee'   => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
