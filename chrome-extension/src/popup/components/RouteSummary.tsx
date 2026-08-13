import type { RoutePreview } from '@/lib/mapsUrl';

export function RouteSummary({ preview }: { preview: RoutePreview }) {
    return (
        <div className="route-summary">
            <div className="route-summary-point">{preview.origin}</div>
            {preview.stops.map((stop) => (
                <div key={stop} className="route-summary-point route-summary-stop">
                    {stop}
                </div>
            ))}
            <div className="route-summary-point">{preview.destination}</div>
        </div>
    );
}
