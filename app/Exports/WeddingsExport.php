<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WeddingsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Couple Name',
            'Email',
            'Phone',
            'Wedding Start Date',
            'Wedding End Date',
            'Total Days',
            'Total Events',
            'City',
            'Venue',
            'Status',
            'Created At',
        ];
    }

    public function map($wedding): array
    {
        $days = $wedding->days;

        return [
            $wedding->id,
            trim("{$wedding->first_name} {$wedding->last_name}"),
            $wedding->email,
            $wedding->phone,
            $this->formatDate($days->first()?->wedding_day_date),
            $this->formatDate($days->last()?->wedding_day_date),
            $days->count(),
            $days->sum(
                fn ($day): int => $day->events->count(),
            ),
            $days->pluck('city')
                ->filter()
                ->unique()
                ->implode(', '),
            $days->pluck('venue_title')
                ->filter()
                ->unique()
                ->implode(', '),
            ucfirst((string) $wedding->status),
            $wedding->created_at?->format('d M Y H:i'),
        ];
    }

    private function formatDate(mixed $value): string
    {
        return $value
            ? Carbon::parse($value)->format('d M Y')
            : '';
    }
}
