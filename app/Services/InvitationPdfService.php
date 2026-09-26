<?php

namespace App\Services;

use App\Models\WeddingBooking;
use Spatie\LaravelPdf\Facades\Pdf;

class InvitationPdfService
{
    /**
     * Build invitation data from booking.
     */
    public function getInvitationData(
        WeddingBooking $booking
    ): array {
        $wedding = $booking->wedding;

        // hasMany returns creators keyed by index, so key by type to look them up by role.
        $creators = $wedding->creators->keyBy('creator_type');
        $bride = $creators->get('bride');
        $groom = $creators->get('groom');

        // dd($bride);

        $coupleName = match ($wedding->creator_type) {
            'other' => $bride && $groom
                ? trim($bride->first_name . ' ' . $bride->last_name)
                . ' & ' . trim($groom->first_name . ' ' . $groom->last_name)
                : null,
            'bride' => $groom
                ? trim($wedding->first_name . ' ' . $wedding->last_name)
                . ' & ' . trim($groom->first_name . ' ' . $groom->last_name)
                : null,
            'groom' => $bride
                ? trim($wedding->first_name . ' ' . $wedding->last_name)
                . ' & ' . trim($bride->first_name . ' ' . $bride->last_name)
                : null,
            default => null,
        };

        $bookingDays = $booking->days->sortBy(
            fn($day) => $day->weddingDay?->wedding_day_date
        );
        $firstBookingDate = $bookingDays->first()?->weddingDay?->wedding_day_date?->format('l, d M Y');
        $lastBookingDate = $bookingDays->last()?->weddingDay?->wedding_day_date?->format('l, d M Y');

        $bookingDates = $lastBookingDate && $lastBookingDate !== $firstBookingDate
            ? "{$firstBookingDate} - {$lastBookingDate}"
            : $firstBookingDate;

        $bookingDayLocations = $bookingDays
            ->unique(fn($day) => $day->weddingDay?->venue_title)
            ->map(function ($day) {
                return collect([
                    $day->weddingDay?->venue_title,
                    $day->weddingDay?->address_line_1,
                    $day->weddingDay?->address_line_2,
                    $day->weddingDay?->city,
                    $day->weddingDay?->state,
                    $day->weddingDay?->post_code,
                ])
                    ->filter()
                    ->implode(', ');
            })
            ->filter()
            ->values();

        $bookingDayLocations = $bookingDayLocations->count() > 1
            ? $bookingDayLocations->map(fn($location, $index) => ($index + 1) . '. ' . $location)->implode('<br> ')
            : $bookingDayLocations->implode('<br> ');

        $data = [
            'creator_type' => $wedding->creator_type ?? '',
            'bride_name' => match ($wedding->creator_type) {
                'bride' => trim($wedding->first_name . ' ' . $wedding->last_name),
                'groom', 'other' => $bride ? trim($bride->first_name . ' ' . $bride->last_name) : '',
                default => '',
            },
            'groom_name' => match ($wedding->creator_type) {
                'groom' => trim($wedding->first_name . ' ' . $wedding->last_name),
                'bride', 'other' => $groom ? trim($groom->first_name . ' ' . $groom->last_name) : '',
                default => '',
            },
            'bride_father' => match ($wedding->creator_type) {
                'bride' => $wedding->fathers_name ?? '',
                'groom', 'other' => $bride ? $bride->fathers_name ?? '' : '',
                default => '',
            },
            'bride_mother' => match ($wedding->creator_type) {
                'bride' => $wedding->mothers_name ?? '',
                'groom', 'other' => $bride ? $bride->mothers_name ?? '' : '',
                default => '',
            },

            'bride_parents' => match ($wedding->creator_type) {
                'bride' => trim(($wedding->fathers_name ?? '') . ' & ' . ($wedding->mothers_name ?? '')),
                'groom', 'other' => $bride ? trim(($bride->fathers_name ?? '') . ' & ' . ($bride->mothers_name ?? '')) : '',
                default => '',
            },

            'groom_father' => match ($wedding->creator_type) {
                'groom' => $wedding->fathers_name ?? '',
                'bride', 'other' => $groom ? $groom->fathers_name ?? '' : '',
                default => '',
            },
            'groom_mother' => match ($wedding->creator_type) {
                'groom' => $wedding->mothers_name ?? '',
                'bride', 'other' => $groom ? $groom->mothers_name ?? '' : '',
                default => '',
            },

            'groom_parents' => match ($wedding->creator_type) {
                'groom' => trim(($wedding->fathers_name ?? '') . ' & ' . ($wedding->mothers_name ?? '')),
                'bride', 'other' => $groom ? trim(($groom->fathers_name ?? '') . ' & ' . ($groom->mothers_name ?? '')) : '',
                default => '',
            },

            'couple_name' => $coupleName ?? '',
            'booking_dates' => $bookingDates ?? '',

            'booking_day_locations' => $bookingDayLocations,

            'guide_full_name' => $wedding->guide_full_name ?? '',
            'guide_phone_number' => $wedding->guide_phone_number ?? '',

            'guest_first_name' => $booking->first_name ?? '',
            'guest_last_name' => $booking->last_name ?? '',

            'guest_full_name' => trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? '')),

            'guest_count' => $booking->number_of_travelers ?? null,
        ];

        return $data;
    }

    /**
     * Generate wedding invitation PDF.
     */
    public function generate(array $data): string
    {
        $backgroundImage = $this->getBackgroundImage();

        return Pdf::view('pdf.wedding-invitation', [
            ...$data,
            'backgroundImage' => $backgroundImage,
        ])
            ->format('a4')
            ->margins(0, 0, 0, 0)
            ->generatePdfContent();
    }

    /**
     * Get invitation background image as Base64.
     */
    private function getBackgroundImage(): string
    {
        $path = public_path(
            'invitations/wedding-invitation.jpeg'
        );

        if (! file_exists($path)) {
            throw new \RuntimeException(
                'Wedding invitation background image not found.'
            );
        }

        $contents = file_get_contents($path);

        $base64 = base64_encode($contents);

        return 'data:image/jpeg;base64,' . $base64;
    }
}
