<?php

namespace Lume\Template;

class Filters
{
    public static function register(Sandbox $sandbox): void
    {
        $sandbox->registerFilter('upper', function ($string) {
            return strtoupper((string) $string);
        });

        $sandbox->registerFilter('lower', function ($string) {
            return strtolower((string) $string);
        });

        $sandbox->registerFilter('capitalize', function ($string) {
            return ucfirst(strtolower((string) $string));
        });

        $sandbox->registerFilter('money', function ($number) {
            return number_format((float) $number, 2) . ' EGP';
        });

        $sandbox->registerFilter('default', function ($value, $default) {
            return empty($value) ? $default : $value;
        });

        $sandbox->registerFilter('json', function ($value) {
            return json_encode($value);
        });
        
        $sandbox->registerFilter('raw', function ($value) {
            // Special filter, bypasses escaping in compiler usually, 
            // but for simplicity we return it directly.
            return $value; 
        });
    }
}
