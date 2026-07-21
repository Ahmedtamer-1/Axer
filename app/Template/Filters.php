<?php

namespace Axer\Template;

class Filters
{
    public static function register(Sandbox $sandbox): void
    {
        $sandbox->registerFilter('upper', function ($string = '') {
            return strtoupper((string) $string);
        });

        $sandbox->registerFilter('lower', function ($string = '') {
            return strtolower((string) $string);
        });

        $sandbox->registerFilter('capitalize', function ($string = '') {
            return ucfirst(strtolower((string) $string));
        });

        $sandbox->registerFilter('money', function ($number = 0) {
            return number_format((float) $number, 2) . ' EGP';
        });

        $sandbox->registerFilter('default', function ($value = null, $default = '') {
            return empty($value) ? $default : $value;
        });

        $sandbox->registerFilter('json', function ($value = null) {
            return json_encode($value);
        });
        
        $sandbox->registerFilter('raw', function ($value = '') {
            return $value; 
        });

        $sandbox->registerFilter('asset_url', function ($path = '') {
            return '/theme-assets/' . ltrim((string)$path, '/');
        });

        $sandbox->registerFilter('date', function ($date = 'now', $format = 'Y-m-d') {
            $time = is_numeric($date) ? (int)$date : strtotime((string)$date);
            return date($format, $time ?: time());
        });
    }
}
