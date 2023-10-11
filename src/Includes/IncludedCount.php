<?php

namespace Spatie\QueryBuilder\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IncludedCount implements IncludeInterface
{
    public function __invoke(Builder|Model $query, string $count)
    {
        $relation = Str::before($count, config('query-builder.count_suffix', 'Count'));

        $query instanceof Model
            ? $query->loadCount($relation)
            : $query->withCount($relation);
    }
}
