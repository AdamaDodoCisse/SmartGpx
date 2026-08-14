import type { RoutePreview } from '@/lib/mapsUrl';
import { MapPinIcon } from './icons';

export function RouteSummary({ preview }: { preview: RoutePreview }) {
    return (
        <div className="route-summary">
            <div className="route-summary-row">
                <MapPinIcon />
                {preview.origin}
            </div>
            {preview.stops.map((stop) => (
                <div key={stop}>
                    <div className="route-summary-connector">
                        <div className="route-summary-connector-line" />
                    </div>
                    <div className="route-summary-row route-summary-stop">
                        <MapPinIcon />
                        {stop}
                    </div>
                </div>
            ))}
            <div className="route-summary-connector">
                <div className="route-summary-connector-line" />
            </div>
            <div className="route-summary-row">
                <MapPinIcon />
                {preview.destination}
            </div>
        </div>
    );
}
