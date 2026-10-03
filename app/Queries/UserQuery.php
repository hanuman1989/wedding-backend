<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserQuery
{
    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, user_type?: bool|int|string|null, status?: bool|int|string|null}  $filters
     * @return Builder<User>
     */
    public function build(array $filters): Builder
    {
        $query = User::query();
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($keyword !== '') {
            $query->where(function (Builder $userQuery) use ($keyword): void {
                $userQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if (filled($filters['start_date'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (filled($filters['end_date'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        foreach (['user_type' => 'is_host', 'status' => 'status'] as $filter => $column) {
            if (($value = $filters[$filter] ?? null) !== null && $value !== '') {
                $query->where($column, filter_var($value, FILTER_VALIDATE_BOOLEAN));
            }
        }

        return $query;
    }

    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, user_type?: bool|int|string|null, status?: bool|int|string|null}  $filters
     * @return Builder<User>
     */
    public function forList(array $filters): Builder
    {
        return $this->build($filters)->orderByDesc('id');
    }

    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, user_type?: bool|int|string|null, status?: bool|int|string|null}  $filters
     * @return Builder<User>
     */
    public function forExport(array $filters): Builder
    {
        return $this->build($filters)->orderByDesc('id');
    }
}
