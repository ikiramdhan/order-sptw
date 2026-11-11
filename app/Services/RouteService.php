<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Jarak & estimasi waktu kurir -> outlet. Rute jalan dari OSRM (gratis); jika gagal pakai garis lurus. */
class RouteService
{
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** @return array{dist_m:int, eta_s:int, route:array, source:string} */
    public static function estimate(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $key = sprintf('route:%.4f,%.4f:%.4f,%.4f', $fromLat, $fromLng, $toLat, $toLng);

        return Cache::remember($key, 25, function () use ($fromLat, $fromLng, $toLat, $toLng) {
            try {
                $url = sprintf('https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F', $fromLng, $fromLat, $toLng, $toLat);
                $res = Http::timeout(4)->acceptJson()->get($url, ['overview' => 'simplified', 'geometries' => 'geojson']);
                $r = $res->json('routes.0');
                if ($res->ok() && $r) {
                    return [
                        'dist_m' => (int) round($r['distance']),
                        'eta_s' => (int) round($r['duration']),
                        'route' => array_map(fn ($c) => [$c[1], $c[0]], $r['geometry']['coordinates'] ?? []),
                        'source' => 'jalan',
                    ];
                }
            } catch (\Throwable $e) {
                // jatuh ke garis lurus
            }

            $d = self::haversine($fromLat, $fromLng, $toLat, $toLng) * 1.3;

            return ['dist_m' => (int) round($d), 'eta_s' => (int) round($d / (25000 / 3600)), 'route' => [], 'source' => 'garis'];
        });
    }
}
