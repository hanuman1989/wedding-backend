<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>New Contact Inquiry</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e0d8;">
                    <tr>
                        <td style="background-color:#8f1827; padding:20px 32px;">
                            <h1 style="margin:0; font-size:18px; color:#ffffff;">New Contact Inquiry</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px; font-size:14px; color:#555555;">
                                You have received a new message from the contact form.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827; width:140px;">Full Name</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">{{ $fullName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; font-size:13px; font-weight:bold; color:#8f1827;">Email Address</td>
                                    <td style="padding:8px 0; font-size:14px; color:#333333;">
                                        <a href="mailto:{{ $email }}" style="color:#333333;">{{ $email }}</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:13px; font-weight:bold; color:#8f1827;">Message</p>
                            <p style="margin:0; font-size:14px; color:#333333; white-space:pre-line; line-height:1.6;">{{ $inquiryMessage }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>