<?php

namespace App\Queries;

use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class WeddingQuery
{
    /**
     * Build the common filtered wedding query.
     *
     * This query is shared by the frontend list and Excel export.
     */
    public function build(array $filters): Builder
    {
        $query = Wedding::query();

        $this->applyDateFilter($query, $filters);
        $this->applyKeywordFilter($query, $filters);
        $this->applySimpleFilters($query, $filters);
        $this->applyDistanceOrdering($query, $filters);

        return $query->orderByDesc('id');
    }

    /**
     * Query optimized for the frontend table.
     */
    public function forList(array $filters): Builder
    {
        return $this->build($filters)
            ->with([
                'creators',
                'thumbnail',
                'user',
                'days' => function (HasMany $query) use ($filters): void {
                    $this->applyDayDateFilter($query, $filters);
                    $query->orderBy('wedding_day_date');
                },
                'days.events',
            ]);
    }

    /**
     * Query optimized for Excel export.
     *
     * Only relationships consumed by WeddingsExport are loaded.
     */
    public function forExport(array $filters): Builder
    {
        return $this->build($filters)
            ->with([
                'days' => function (HasMany $query) use ($filters): void {
                    $this->applyDayDateFilter($query, $filters);
                    $query->orderBy('wedding_day_date');
                },
                'days.events',
            ]);
    }

    /**
     * Apply the wedding-level date filter.
     *
     * A wedding matches when it has at least one day in the requested range.
     */
    private function applyDateFilter(
        Builder $query,
        array $filters,
    ): void {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if ($startDate === null && $endDate === null) {
            return;
        }

        $query->whereHas('days', function (Builder $dayQuery) use (
            $startDate,
            $endDate,
        ): void {
            $this->applyDayDateFilter(
                $dayQuery,
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            );
        });
    }

    /**
     * Apply date constraints to either:
     * - an Eloquent Builder used by whereHas(), or
     * - a HasMany relation used by with().
     *
     * Laravel eager-loading constraints receive the relationship object,
     * while whereHas() constraints receive an Eloquent Builder.
     */
    private function applyDayDateFilter(
        Builder|HasMany $query,
        array $filters,
    ): void {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if ($startDate !== null && $startDate !== '') {
            $query->whereDate('wedding_day_date', '>=', $startDate);
        }

        if ($endDate !== null && $endDate !== '') {
            $query->whereDate('wedding_day_date', '<=', $endDate);
        }
    }

    /**
     * Apply keyword filtering across wedding and wedding-day fields.
     */
    private function applyKeywordFilter(
        Builder $query,
        array $filters,
    ): void {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($keyword === '') {
            return;
        }

        $like = "%{$keyword}%";

        $query->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereRaw(
                    "CONCAT(first_name, ' ', last_name) LIKE ?",
                    [$like],
                )
                ->orWhereHas('days', function (Builder $dayQuery) use (
                    $like,
                ): void {
                    $dayQuery
                        ->where('address_line_1', 'like', $like)
                        ->orWhere('address_line_2', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('state', 'like', $like)
                        ->orWhere('country', 'like', $like)
                        ->orWhere('post_code', 'like', $like)
                        ->orWhere('landmark_near', 'like', $like)
                        ->orWhere('venue_title', 'like', $like);
                });
        });
    }

    /**
     * Apply simple wedding-level filters.
     */
    private function applySimpleFilters(
        Builder $query,
        array $filters,
    ): void {
        if (($status = $filters['status'] ?? null) !== null && $status !== '') {
            $query->where('status', (string) $status);
        }

        if (($userId = $filters['user_id'] ?? null) !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
    }

    /**
     * Add nearest-wedding-day distance and use it for ordering.
     *
     * The subquery returns one scalar distance for each wedding, so the
     * wedding result set is not duplicated.
     */
    private function applyDistanceOrdering(
        Builder $query,
        array $filters,
    ): void {
        if (
            ! isset($filters['latitude'], $filters['longitude'])
            || $filters['latitude'] === ''
            || $filters['longitude'] === ''
        ) {
            return;
        }

        $latitude = (float) $filters['latitude'];
        $longitude = (float) $filters['longitude'];
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $distanceQuery = $this->nearestDayDistanceQuery(
            $latitude,
            $longitude,
            $startDate,
            $endDate,
        );

        $query
            ->addSelect([
                'distance_km' => $distanceQuery,
            ])
            ->orderBy($distanceQuery);
    }

    /**
     * Return the nearest matching wedding-day distance in kilometres.
     *
     * The ACOS input is clamped to [-1, 1] to protect against floating-point
     * precision errors.
     */
    private function nearestDayDistanceQuery(
        float $latitude,
        float $longitude,
        ?string $startDate,
        ?string $endDate,
    ): QueryBuilder {
        $distanceSql = <<<'SQL'
            6371 * ACOS(
                LEAST(
                    1,
                    GREATEST(
                        -1,
                        COS(RADIANS(?))
                        * COS(RADIANS(wedding_days.latitude))
                        * COS(
                            RADIANS(wedding_days.longitude) - RADIANS(?)
                        )
                        + SIN(RADIANS(?))
                        * SIN(RADIANS(wedding_days.latitude))
                    )
                )
            )
        SQL;

        $query = WeddingDay::query()
            ->selectRaw(
                $distanceSql,
                [$latitude, $longitude, $latitude],
            )
            ->whereColumn(
                'wedding_days.wedding_id',
                'weddings.id',
            )
            ->whereNotNull('wedding_days.latitude')
            ->whereNotNull('wedding_days.longitude');

        if ($startDate !== null && $startDate !== '') {
            $query->whereDate('wedding_day_date', '>=', $startDate);
        }

        if ($endDate !== null && $endDate !== '') {
            $query->whereDate('wedding_day_date', '<=', $endDate);
        }

        return $query
            ->orderByRaw(
                $distanceSql,
                [$latitude, $longitude, $latitude],
            )
            ->limit(1)
            ->getQuery();
    }
}
