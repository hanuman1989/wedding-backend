<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 210mm;
            height: 297mm;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .invitation {
            position: relative;

            width: 210mm;
            height: 297mm;

            background-image: url("{{ $backgroundImage }}");

            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;

            overflow: hidden;
        }

        /* ===========================
           BRIDE
        ============================ */

        .bride-name {
            position: absolute;
            top: 57mm;
            left: 4mm;
            width: 73mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 23px;
            font-style: italic;
            font-weight: bold;

            color: #8f1827;
        }


        /* ===========================
           GROOM
        ============================ */

        .groom-name {
            position: absolute;
            top: 57mm;
            right: 30mm;
            width: 73mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 23px;
            font-style: italic;
            font-weight: bold;

            color: #8f1827;
        }


        /* ===========================
           BRIDE PARENTS
        ============================ */

        .bride-parents {
            position: absolute;
            top: 66mm;
            left: 43mm;
            width: 50mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 16px;
            color: #333;
        }


        /* ===========================
           GROOM PARENTS
        ============================ */

        .groom-parents {
            position: absolute;
            top: 66mm;
            right: 17mm;
            width: 55mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 16px;
            color: #333;
        }


        /* ===========================
           WEDDING DATE
        ============================ */

        .wedding-date {
            position: absolute;
            top: 108mm;
            left: 13mm;
            width: 41mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }


        /* ===========================
           WEDDING TIME
        ============================ */

        .wedding-time {
            position: absolute;

            top: 124mm;
            left: 13mm;

            width: 47mm;

            text-align: center;

            font-size: 10px;

            color: #333;
        }


        /* ===========================
           VENUE
        ============================ */

        .venue-name {
            position: absolute;
            top: 154mm;
            left: 13mm;
            width: 47mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 15px;
            font-weight: bold;
            color: #333;
            line-height: 1.3;
        }


        .venue-address {
            position: absolute;
            top: 165mm;
            left: 11mm;
            width: 51mm;
            text-align: center;
            font-size: 8px;
            line-height: 1.4;
            color: #333;
        }


        /* ===========================
           GUEST RECEIVER
        ============================ */

        .receiver-name {
            position: absolute;
            top: 158mm;
            right: 11mm;
            width: 52mm;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            line-height: 1.4;
            color: #333;
        }


        .receiver-phone {
            position: absolute;
            top: 165mm;
            right: 11mm;
            width: 52mm;
            text-align: center;
            font-size: 15px;
            color: #333;
        }


        /* ===========================
           GUEST
        ============================ */

        .guest-name {
            position: absolute;
            top: 231mm;
            left: 52mm;
            width: 106mm;
            text-align: center;
            font-family: DejaVu Serif, serif;
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }


        .guest-count {
            position: absolute;
            top: 238mm;
            left: 52mm;
            width: 106mm;
            text-align: center;
            font-size: 9px;
            color: #333;
        }
    </style>
</head>

<body>

    <div class="invitation">

        <div class="bride-name">
            {{ $bride_name }}
        </div>

        <div class="groom-name">
            {{ $groom_name }}
        </div>

        <div class="bride-parents">
            {{ $bride_parents }}
        </div>

        <div class="groom-parents">
            {{ $groom_parents }}
        </div>

        <div class="wedding-date">
            {{ $booking_dates }}
        </div>


        <div class="venue-name">
            {{ $booking_day_locations }}
        </div>


        <div class="receiver-name">
            {{ $guide_full_name }}
        </div>

        <div class="receiver-phone">
            {{ $guide_phone_number }}
        </div>

        <div class="guest-name">
            {{ $guest_full_name }}
        </div>
        <!-- 
        @if($guest_count)
        <div class="guest-count">
            {{ $guest_count }}
            {{ $guest_count == 1 ? 'Guest' : 'Guests' }}
        </div>
        @endif -->

    </div>

</body>

</html>