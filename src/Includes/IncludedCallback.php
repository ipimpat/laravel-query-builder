<?php

namespace Spatie\QueryBuilder\Includes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IncludedCallback implements IncludeInterface
{
    protected Closure $callback;

    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Builder|Model $query, string $relation)
    {
        $query instanceof Model
            ? $query->load([$relation => $this->callback])
            : $query->with([$relation => $this->callback]);
    }
}
