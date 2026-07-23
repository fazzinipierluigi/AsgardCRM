<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
    public function show(Request $request, string $variant, string $name): Response
    {
        $svg = icon($name, $variant);

        abort_if($svg === '', 404);

        // Tabler SVGs paint with stroke/fill="currentColor", which an
        // inline <svg> resolves against the surrounding page's CSS color
        // — but this endpoint is also loaded as a standalone image (e.g.
        // MaxGraph node overlays in the workflow builder), where there is
        // no surrounding CSS to inherit from. `color` lets a caller bake
        // in a fixed color for that standalone case.
        $color = $request->query('color');

        if (is_string($color) && preg_match('/^[0-9a-fA-F]{3,8}$/', $color) === 1) {
            $svg = str_replace('currentColor', "#{$color}", $svg);
        }

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
