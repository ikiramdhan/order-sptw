<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Ambil koordinat dari tempelan: link Google Maps (panjang atau pendek maps.app.goo.gl) atau "lat, lng". */
class MapsLink
{
    /** Jejak terakhir pembacaan link (untuk diagnosa bila gagal). */
    public static string $trace = '';

    private const BODY_PATTERNS = [
        '/@(-?\d{1,2}\.\d{3,}),(-?\d{1,3}\.\d{3,})/',
        '/!3d(-?\d{1,2}\.\d{3,})!4d(-?\d{1,3}\.\d{3,})/',
        '/(?:center|markers)=(-?\d{1,2}\.\d{3,})(?:%2C|,)(-?\d{1,3}\.\d{3,})/i',
        '/\[null,null,(-?\d{1,2}\.\d{3,}),(-?\d{1,3}\.\d{3,})\]/',
        '/"(?:lat|latitude)"\s*:\s*(-?\d{1,2}\.\d{3,}).{0,20}"(?:lng|lon|longitude)"\s*:\s*(-?\d{1,3}\.\d{3,})/s',
    ];

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

        self::$trace = '';
        try {
            for ($i = 0; $i < 8; $i++) {
                [$lat, $lng] = self::parse($url);
                if ($lat !== null) {
                    return [$lat, $lng];
                }
                $res = Http::timeout(8)->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', 'Accept-Language' => 'id-ID,id;q=0.9'])
                    ->get($url);
                self::$trace = parse_url($url, PHP_URL_HOST).' → HTTP '.$res->status();
                $next = $res->header('Location');
                if ($res->redirect() && $next) {
                    $url = str_starts_with($next, 'http') ? $next : (parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$next);

                    continue;
                }
                if ($res->ok()) {
                    $body = $res->body();
                    foreach (self::BODY_PATTERNS as $re) {
                        if (preg_match($re, $body, $mm) && abs((float) $mm[1]) <= 90 && abs((float) $mm[2]) <= 180) {
                            return [(float) $mm[1], (float) $mm[2]];
                        }
                    }
                    // halaman perantara berisi tautan lanjutan (mis. consent / interstitial)
                    if (preg_match('~<a[^>]+href="(https?://(?:www\.)?google\.[^"]+/maps[^"]*)"~i', $body, $mm) && $mm[1] !== $url) {
                        $url = html_entity_decode($mm[1]);

                        continue;
                    }
                }
                break;
            }
        } catch (\Throwable $e) {
            self::$trace = 'error: '.class_basename($e);
            report($e);
        }

        return [null, null];
    }
}
