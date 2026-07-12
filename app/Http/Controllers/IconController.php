<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves a single Tabler icon's raw SVG markup — the HTTP-accessible twin
 * of the icon() Blade/PHP helper (app/helpers.php), used by the JS icon()
 * helper (resources/js/icon.js) for markup built client-side. These are
 * just the open-source @tabler/icons SVG files already vendored into
 * node_modules, not app data, so the response is cacheable.
 */
class IconController extends Controller
{
    public function show(string $variant, string $name): Response
    {
        $svg = icon($name, $variant);

        abort_if($svg === '', 404);

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
