<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Ambil koordinat dari tempelan: link Google Maps (panjang atau pendek maps.app.goo.gl) atau "lat, lng". */
class MapsLink
{
    private const PATTERNS = [
        '/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/',
        '/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/',
        '/[?&](?:q|ll|query|destination|center)=(-?\d{1,2}\.\d+)[, +]+(-?\d{1,3}\.\d+)/',
        '/^\s*(-?\d{1,2}\.\d+)\s*[,;\s]\s*(-?\d{1,3}\.\d+)\s*$/',
    ];

    /** @return array{0: ?float, 1: ?float} */
    public static function parse(string $text): array
    {
        $text = urldecode(trim($text));
        foreach (self::PATTERNS as $re) {
            if (preg_match($re, $text, $m) && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return [null, null];
    }

    /** @return array{0: ?float, 1: ?float} */
    public static function resolve(string $text): array
    {
        [$lat, $lng] = self::parse($text);
        if ($lat !== null) {
            return [$lat, $lng];
        }

        // Ambil URL dari teks (mis. "Lihat di Maps: https://maps.app.goo.gl/xxx"), ikuti pengalihan hingga 6 kali.
        if (! preg_match('~https?://[^\s]+~i', $text, $m)) {
            return [null, null];
        }
        $url = rtrim($m[0], '.,)');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! preg_match('/(^|\.)(google\.[a-z.]+|goo\.gl|g\.co)$/', $host)) {
            return [null, null];
        }

        try {
            for ($i = 0; $i < 6; $i++) {
                [$lat, $lng] = self::parse($url);
                if ($lat !== null) {
                    return [$lat, $lng];
                }
                $res = Http::timeout(6)->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36'])
                    ->get($url);
                $next = $res->header('Location');
                if ($res->redirect() && $next) {
                    $url = str_starts_with($next, 'http') ? $next : (parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$next);

                    continue;
                }
                if ($res->ok()) {
                    foreach (['/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', '/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/'] as $re) {
                        if (preg_match($re, $res->body(), $mm)) {
                            return [(float) $mm[1], (float) $mm[2]];
                        }
                    }
                }
                break;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [null, null];
    }
}
