<?php

namespace Axer\Support;

/**
 * Multibyte-safe string helpers.
 *
 * The controllers were doing this work inline with byte-oriented functions
 * (substr, strtolower, preg_replace on ASCII ranges), which mangles Arabic
 * text — the primary language for an EGP storefront.
 */
class Str
{
    /**
     * URL slug.
     *
     * The old inline version was:
     *   strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)))
     * which had two bugs: trim() strips whitespace, not the dashes the
     * regex just produced (so slugs arrived as "-my-product-"), and a
     * fully non-ASCII name collapsed to a single "-".
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Transliterate where the intl extension allows it, so "Café" and
        // "قميص" become useful ASCII slugs rather than nothing.
        if (function_exists('transliterator_transliterate')) {
            $transliterated = @transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

            if (is_string($transliterated) && trim($transliterated) !== '') {
                $value = $transliterated;
            }
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

            if (is_string($converted) && trim($converted, "?- \t\n") !== '') {
                $value = $converted;
            }
        }

        $value = mb_strtolower($value, 'UTF-8');

        // Keep unicode letters/digits so a non-transliterable script still
        // produces a readable (and valid) URL rather than an empty one.
        $value = preg_replace('/[^\p{L}\p{N}]++/u', $separator, $value) ?? '';
        $value = preg_replace('/' . preg_quote($separator, '/') . '{2,}/', $separator, $value) ?? '';

        return trim($value, $separator);
    }

    /**
     * Slug guaranteed to be non-empty, for when it must go in a URL.
     */
    public static function slugOrFallback(string $value, string $prefix = 'item'): string
    {
        $slug = self::slug($value);

        return $slug !== '' ? $slug : $prefix . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    /**
     * Truncate on a character boundary, preferring to break at a space.
     *
     * substr() at a fixed byte offset splits multibyte characters in half
     * and renders as a replacement glyph.
     */
    public static function truncate(string $value, int $length = 100, string $suffix = '…'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($length <= 0 || mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        $cut = mb_substr($value, 0, $length, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');

        // Only honour the word boundary if it is not absurdly early.
        if ($lastSpace !== false && $lastSpace > (int) ($length * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($cut) . $suffix;
    }

    /**
     * Plain-text excerpt from HTML: strip markup first, then truncate.
     *
     * Doing it the other way round (as the storefront did) can cut through
     * a tag and leave broken markup in the output.
     */
    public static function excerpt(?string $html, int $length = 100): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::truncate($text, $length);
    }

    public static function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length, 'UTF-8');
    }

    /**
     * Human-readable order reference, e.g. AX-20260728-4F2A9C.
     */
    public static function orderNumber(string $prefix = 'AX'): string
    {
        return sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }
}
