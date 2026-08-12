/**
 * Inline SVG markup for a Tabler icon, fetched from IconController — the
 * HTTP twin of the PHP icon() helper (app/helpers.php), used for markup
 * built client-side. Results are cached in memory per page load, so
 * requesting the same icon twice only hits the network once.
 *
 * New-code rule: never load the icon webfont — always print an icon's
 * actual SVG content into the page via this helper (or the PHP one, for
 * markup built server-side in Blade).
 */
const cache = new Map();

export function icon(name, variant = 'outline') {
    const key = `${variant}/${name}`;

    if (!cache.has(key)) {
        cache.set(
            key,
            fetch(`${window.ICONS_BASE_URL}/${variant}/${name}`)
                .then((response) => (response.ok ? response.text() : ''))
                .catch(() => '')
        );
    }

    return cache.get(key);
}
