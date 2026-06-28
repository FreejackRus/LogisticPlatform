<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocoderController extends Controller
{
    public function suggest(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return response()->json([
            'suggestions' => $geocoding->suggestAddresses($data['q'], $data['limit'] ?? null),
        ]);
    }
}
