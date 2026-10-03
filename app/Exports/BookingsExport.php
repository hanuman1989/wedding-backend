<?php

namespace App\Exports;

use App\Models\Wedding;
use App\Models\WeddingBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class BookingsExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query,
    ) {}

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

    public function headings(): array
    {
        return [
            'Booking ID',
            'Guest Name',
            'Wedding',
            'Wedding Dates',
            'Guests',
            'Guest Email',
            'Guest Phone',
            'Booking Price Per Guest',
            'Total Amount',
            'Platform Fee',
            'Payout Amount',
            'Payment Status',
            'Booking Status',
            'Booked At',
        ];
    }

    public function map($booking): array
    {
        $payoutAmount = (float) $booking->subtotal - (float) $booking->platform_fee;

        return [
            $booking->booking_number ?: $booking->id,
            trim("{$booking->first_name} {$booking->last_name}"),
            $this->weddingName($booking->wedding),
            $this->weddingDates($booking),
            (int) $booking->number_of_travelers,
            $booking->email,
            $booking->phone,
            (float) $booking->price_per_person,
            (float) $booking->subtotal,
            (float) $booking->platform_fee,
            $payoutAmount,
            ucfirst((string) $booking->payment?->status),
            ucfirst((string) $booking->status),
            $booking->created_at?->format('d M Y H:i'),
        ];
    }

    private function weddingName(?Wedding $wedding): string
    {
        if (! $wedding) {
            return '';
        }

        $creators = $wedding->creators->keyBy('creator_type');
        $primaryName = trim("{$wedding->first_name} {$wedding->last_name}");
        $bride = $creators->get('bride');
        $groom = $creators->get('groom');
        $brideName = $bride ? trim("{$bride->first_name} {$bride->last_name}") : null;
        $groomName = $groom ? trim("{$groom->first_name} {$groom->last_name}") : null;

        return match ($wedding->creator_type) {
            'other' => $brideName && $groomName
                ? $brideName.' & '.$groomName
                : $primaryName,
            'bride' => $groomName
                ? $primaryName.' & '.$groomName
                : $primaryName,
            'groom' => $brideName
                ? $primaryName.' & '.$brideName
                : $primaryName,
            default => $primaryName,
        };
    }

    private function weddingDates(WeddingBooking $booking): string
    {
        $dates = $booking->days->pluck('weddingDay.wedding_day_date')
            ->filter()
            ->sortBy(fn (Carbon $date): string => $date->toDateString());
        $firstDate = $dates->first()?->format('d M Y');
        $lastDate = $dates->last()?->format('d M Y');

        return $firstDate && $lastDate && $firstDate !== $lastDate
            ? $firstDate.' - '.$lastDate
            : ($firstDate ?? '');
    }
}
