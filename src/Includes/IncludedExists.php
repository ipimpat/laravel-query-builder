<?php

namespace Spatie\QueryBuilder\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IncludedExists implements IncludeInterface
{
    public function __invoke(Builder|Model $query, string $exists)
    {
        $exists = Str::before($exists, config('query-builder.exists_suffix', 'Exists'));
        $casts = [
            "{$exists}_exists" => 'boolean',
        ];

        $query instanceof Model
            ? $query->loadExists($exists, fn (Builder $query) => $query->withCasts($casts))
            : $query->withExists($exists)->withCasts($casts);
    }
}
