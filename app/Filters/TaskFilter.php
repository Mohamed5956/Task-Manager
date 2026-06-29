<?php

namespace App\Filters;

use App\Enums\TaskStatus;

class TaskFilter extends QueryFilter
{
    public function status(string $value): void
    {
        $this->builder->where('status', TaskStatus::from($value));
    }

    public function search(string $value): void
    {
        $this->builder->where('title', 'like', "%{$value}%");
    }

    public function dueDate(string $value): void
    {
        $this->builder->whereDate('due_date', $value);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['title', 'status', 'due_date', 'created_at'];
        if (in_array($column, $allowed)) {
            $dir = $this->request->query('sort_dir', 'asc');
            $this->builder->orderBy($column, $dir === 'desc' ? 'desc' : 'asc');
        }
    }
}