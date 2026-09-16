<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\WeddingDetailResource;

class WeddingDetailController extends Controller
{
    public function show(Request $request, Wedding $wedding): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new WeddingDetailResource(
                $wedding->load([
                        'creators',
                        'images',
                        'days.events'
                        ])
            ),
            'message' => 'Wedding details retrieved successfully.',
        ]);
    }

    public function showOld(Request $request, Wedding $wedding): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new WeddingDetailResource(
                $wedding->load([
                    'creators',
                    'images',
                    'days' => fn ($query) => $query
                        ->whereDate('wedding_day_date', '>=', today())
                        ->orderBy('wedding_day_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->with('events'),
                ])
            ),
            'message' => 'Wedding details retrieved successfully.',
        ]);
    }
}
