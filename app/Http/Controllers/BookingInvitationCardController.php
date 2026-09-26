<?php

namespace App\Http\Controllers;

use App\Models\WeddingBooking;
use App\Services\InvitationPdfService;

class BookingInvitationCardController extends Controller
{
    /**
     * Preview invitation PDF in browser.
     */
    public function preview(
        WeddingBooking $booking,
        InvitationPdfService $pdfService
    ) {
        $booking->load([
            'wedding.creators',
            'days.weddingDay',
            'days.weddingDay.events',
            'payment',
        ]);
        $data = $pdfService->getInvitationData(
            $booking
        );

        $pdf = $pdfService->generate($data);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="wedding-invitation.pdf"',
        ]);
    }

    /**
     * Download invitation PDF as attachment.
     */
    public function download(
        WeddingBooking $booking,
        InvitationPdfService $pdfService
    ) {

        $data = $pdfService->getInvitationData(
            $booking
        );

        $pdf = $pdfService->generate($data);

        $filename =
            'wedding-invitation-' .
            $booking->id .
            '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',

            'Content-Disposition' => 'attachment; filename="' .
                $filename .
                '"',

            'Content-Length' => strlen($pdf),
        ]);
    }
}
