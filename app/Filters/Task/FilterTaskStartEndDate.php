<?php

namespace App\Filters\Task;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FilterTaskStartEndDate implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        // Split value into start and end dates
        $dates = $value;
        $startDate = $dates[0] ?? null;
        $endDate = $dates[1] ?? null;

        return $query->when($startDate, function ($query) use ($startDate) {
                $query->whereDate('start_date', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->whereDate('end_date', '<=', $endDate);
            });
    }
}
