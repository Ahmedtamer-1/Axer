<?php

namespace Axer\Template;

class Tags
{
    public static function register(Sandbox $sandbox): void
    {
        $sandbox->registerTag('section', function ($name) {
            // Render a specific section based on the builder JSON
            // For now, return a placeholder
            return "<!-- Section: {$name} -->";
        });
        
        $sandbox->registerTag('snippet', function ($name) {
            return "<!-- Snippet: {$name} -->";
        });
    }
}
