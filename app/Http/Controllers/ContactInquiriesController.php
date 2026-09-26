<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactInquiryRequest;
use App\Mail\ContactInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ContactInquiriesController extends Controller
{
    /**
     * Send a contact inquiry email to the admin.
     */
    public function store(ContactInquiryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Mail::to(config('mail.admin_address'))->send(
            new ContactInquiryMail(
                $validated['full_name'],
                $validated['email'],
                $validated['message'],
            )
        );

        return response()->json([
            'status' => true,
            'message' => 'Your message has been sent successfully.',
        ], 201);
    }
}
