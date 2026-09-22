<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MapSatelliteStyleController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'version' => 8,
            'sources' => [
                'satellite' => [
                    'type'        => 'raster',
                    'tiles'       => [
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                    ],
                    'tileSize'    => 256,
                    'maxzoom'     => 19,
                    'attribution' => 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
                ],
            ],
            'layers' => [
                [
                    'id'      => 'satellite-layer',
                    'type'    => 'raster',
                    'source'  => 'satellite',
                    'minzoom' => 0,
                    'maxzoom' => 22,
                ],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}