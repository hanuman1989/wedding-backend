<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booking Confirmed</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e0d8;">
                    <tr>
                        <td style="background-color:#8f1827; padding:20px 32px;">
                            <h1 style="margin:0; font-size:18px; color:#ffffff;">Booking Confirmed</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px; font-size:14px; color:#555555;">
                                Hi {{ $booking->first_name }}, your payment was successful and your booking is
                                confirmed. Your invitation card is attached to this email.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827; width:180px;">
                                        Booking Number</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        {{ $booking->booking_number }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827;">
                                        Couple</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        {{ $invitation['couple_name'] ?? '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827;">
                                        Dates</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        {{ $invitation['booking_dates'] ?? '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827;">
                                        Venue</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        {!! $invitation['booking_day_locations'] ?? '' !!}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827;">
                                        Guests</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        {{ $booking->number_of_travelers }}
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 12px; font-size:15px; color:#333333;">Invoice</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="border-collapse:collapse; margin-bottom:8px;">
                                <tr>
                                    <td style="padding:8px 0; font-size:14px; color:#555555; border-bottom:1px solid #eeeeee;">
                                        Price per person</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333; text-align:right; border-bottom:1px solid #eeeeee;">
                                        {{ number_format($booking->price_per_person, 2) }}
                                        {{ strtoupper($booking->currency) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:14px; color:#555555; border-bottom:1px solid #eeeeee;">
                                        Travelers</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333; text-align:right; border-bottom:1px solid #eeeeee;">
                                        &times; {{ $booking->number_of_travelers }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:14px; color:#555555; border-bottom:1px solid #eeeeee;">
                                        Subtotal</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333; text-align:right; border-bottom:1px solid #eeeeee;">
                                        {{ number_format($booking->subtotal, 2) }}
                                        {{ strtoupper($booking->currency) }}
                                    </td>
                                </tr>
                                @if ($booking->payment_fee > 0)
                                <tr>
                                    <td style="padding:8px 0; font-size:14px; color:#555555; border-bottom:1px solid #eeeeee;">
                                        Payment fee</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333; text-align:right; border-bottom:1px solid #eeeeee;">
                                        {{ number_format($booking->payment_fee, 2) }}
                                        {{ strtoupper($booking->currency) }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 0; font-size:15px; font-weight:bold; color:#8f1827;">
                                        Total Paid</td>
                                    <td style="padding:12px 0; font-size:15px; font-weight:bold; color:#8f1827; text-align:right;">
                                        {{ number_format($booking->total_amount, 2) }}
                                        {{ strtoupper($booking->currency) }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:12px; color:#999999;">
                                This email confirms your payment. Please keep the attached invitation card for
                                your records.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>