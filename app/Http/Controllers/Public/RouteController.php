<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\OsrmDistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side route proxy. The browser never contacts OSRM directly —
 * this is the endpoint the browser CAN call if it needs a route
 * outside of Livewire.
 *
 * The Livewire explore-map SFC uses OsrmDistanceService::fetchRoute()
 * directly (no HTTP hop) — this controller exists for other consumers
 * and shares the SAME cache.
 */
class RouteController extends Controller
{
    public function __invoke(Request $request, OsrmDistanceService $osrm): JsonResponse
    {
        $validated = $request->validate([
            'start_lat' => 'required|numeric|between:-90,90',
            'start_lng' => 'required|numeric|between:-180,180',
            'end_lat'   => 'required|numeric|between:-90,90',
            'end_lng'   => 'required|numeric|between:-180,180',
            'profile'   => 'nullable|in:driving,walking,cycling',
        ]);

        $route = $osrm->fetchRoute(
            (float) $validated['start_lat'],
            (float) $validated['start_lng'],
            (float) $validated['end_lat'],
            (float) $validated['end_lng'],
            $validated['profile'] ?? 'driving',
        );

        if ($route === null) {
            return response()->json([
                'error' => 'Routing service unavailable for this route.',
            ], 503);
        }

        return response()->json($route);
    }
}