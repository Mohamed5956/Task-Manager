<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $fillable = ['tenant_id', 'user_id', 'title', 'description', 'status', 'due_date'];

    protected function casts(): array
    {
        return [
            'status'   => TaskStatus::class,
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($builder) {
            if (app()->has('tenant')) {
                $builder->where('tasks.tenant_id', app('tenant')->id);
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
