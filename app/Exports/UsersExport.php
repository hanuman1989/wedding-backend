<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class UsersExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Phone', 'User Type', 'Status', 'Created At', 'Updated At'];
    }

    /** @return array<int, int|string|null> */
    public function map(mixed $user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->phone,
            $user->is_host ? 'Host' : 'Guest',
            $user->status ? 'Active' : 'Inactive',
            $user->created_at?->format('d M Y H:i'),
            $user->updated_at?->format('d M Y H:i'),
        ];
    }
}
