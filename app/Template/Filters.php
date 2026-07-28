<?php

namespace Axer\Template;

use Axer\Support\Str;

class Filters
{
    /** Overridden from store settings at render time. */
    public static string $currency = 'EGP';

    public static function register(Sandbox $sandbox): void
    {
        // -- Text -------------------------------------------------------
        // All of these are multibyte-aware: the previous strtoupper /
        // ucfirst / substr versions corrupted Arabic product copy, which
        // matters for an EGP storefront.

        $sandbox->registerFilter('upper', static fn($s = '') => mb_strtoupper((string) $s, 'UTF-8'));
        $sandbox->registerFilter('lower', static fn($s = '') => mb_strtolower((string) $s, 'UTF-8'));

        $sandbox->registerFilter('capitalize', static function ($s = '') {
            $s = mb_strtolower((string) $s, 'UTF-8');

            return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
        });

        $sandbox->registerFilter('trim', static fn($s = '') => trim((string) $s));

        $sandbox->registerFilter('truncate', static function ($s = '', $length = 100, $suffix = '…') {
            return Str::truncate((string) $s, (int) $length, (string) $suffix);
        });

        $sandbox->registerFilter('strip_tags', static fn($s = '') => strip_tags((string) $s));

        $sandbox->registerFilter('nl2br', static fn($s = '') => nl2br(
            htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8')
        ));

        $sandbox->registerFilter('slug', static fn($s = '') => Str::slug((string) $s));

        $sandbox->registerFilter('escape', static fn($s = '') => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));

        // -- Numbers ----------------------------------------------------

        $sandbox->registerFilter('money', static function ($number = 0, $currency = null) {
            return number_format((float) $number, 2) . ' ' . ($currency ?? self::$currency);
        });

        $sandbox->registerFilter('number', static fn($n = 0, $decimals = 0) => number_format(
            (float) $n,
            (int) $decimals
        ));

        $sandbox->registerFilter('round', static fn($n = 0, $precision = 0) => round((float) $n, (int) $precision));

        // -- Collections ------------------------------------------------

        $sandbox->registerFilter('length', static function ($value = null) {
            if (is_array($value) || $value instanceof \Countable) {
                return count($value);
            }

            return mb_strlen((string) $value, 'UTF-8');
        });

        $sandbox->registerFilter('first', static function ($value = null) {
            if (is_array($value)) {
                return $value === [] ? null : reset($value);
            }

            return mb_substr((string) $value, 0, 1, 'UTF-8');
        });

        $sandbox->registerFilter('join', static function ($value = null, $glue = ', ') {
            return is_array($value) ? implode((string) $glue, $value) : (string) $value;
        });

        // -- Misc -------------------------------------------------------

        $sandbox->registerFilter('default', static function ($value = null, $default = '') {
            // Treat 0 and "0" as real values rather than "missing" — a
            // product priced at 0 should not silently fall back.
            if ($value === null || $value === '' || $value === false || $value === []) {
                return $default;
            }

            return $value;
        });

        $sandbox->registerFilter('json', static fn($value = null) => json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));

        $sandbox->registerFilter('raw', static fn($value = '') => $value);

        $sandbox->registerFilter('asset_url', static function ($path = '') {
            return '/theme-assets/' . ltrim((string) $path, '/');
        });

        $sandbox->registerFilter('date', static function ($date = 'now', $format = 'Y-m-d') {
            if ($date === null || $date === '') {
                return '';
            }

            $time = is_numeric($date) ? (int) $date : strtotime((string) $date);

            return $time === false ? '' : date((string) $format, $time);
        });
    }
}
