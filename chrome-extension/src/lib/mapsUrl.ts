const DIRECTIONS_HOST_PATTERN = /^(www\.)?google\.[a-z.]+$/i;
const SHORT_LINK_HOSTS = new Set(['maps.app.goo.gl', 'goo.gl']);

export interface RoutePreview {
    origin: string;
    destination: string;
    stops: string[];
}

function parseUrl(rawUrl: string): URL | null {
    try {
        return new URL(rawUrl);
    } catch {
        return null;
    }
}

/**
 * Whether the current tab is plausibly a Google Maps route the extension can convert.
 * Short links can't be inspected client-side (they redirect server-side) so they're accepted
 * as-is — the backend resolves and validates them the same way it does for the web flow.
 */
export function isGoogleMapsRouteUrl(rawUrl: string): boolean {
    const url = parseUrl(rawUrl);
    if (null === url) {
        return false;
    }

    if (SHORT_LINK_HOSTS.has(url.hostname)) {
        return true;
    }

    if (!DIRECTIONS_HOST_PATTERN.test(url.hostname)) {
        return false;
    }

    if (url.pathname.startsWith('/maps/dir/')) {
        return true;
    }

    return '/maps' === url.pathname && '1' === url.searchParams.get('api') && url.searchParams.has('destination');
}

/**
 * Best-effort origin/destination extraction for the popup preview, before any credit is
 * spent. Only handles the path-segment URL format (.../maps/dir/Origin/Destination/@lat,lng,z);
 * the query-param format and short links have no client-side-inspectable address labels, so the
 * popup shows a generic "Export this route" prompt instead — the real, authoritative parse
 * happens server-side in GoogleMapsUrlParser once the user clicks Export.
 */
export function parseRoutePreview(rawUrl: string): RoutePreview | null {
    const url = parseUrl(rawUrl);
    if (null === url || !DIRECTIONS_HOST_PATTERN.test(url.hostname) || !url.pathname.startsWith('/maps/dir/')) {
        return null;
    }

    const segments = url.pathname
        .slice('/maps/dir/'.length)
        .split('/')
        .filter((segment) => segment.length > 0 && !segment.startsWith('@') && !segment.startsWith('data='))
        .map((segment) => decodeURIComponent(segment.replace(/\+/g, ' ')));

    if (segments.length < 2) {
        return null;
    }

    return {
        origin: segments[0],
        destination: segments[segments.length - 1],
        stops: segments.slice(1, -1),
    };
}
