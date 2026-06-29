<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    protected Builder $builder;

    public function __construct(protected readonly Request $request) {}

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->query() as $name => $value) {
            $method = str($name)->camel()->toString();
            if (method_exists($this, $method) && filled($value)) {
                $this->$method($value);
            }
        }

        return $this->builder;
    }
}