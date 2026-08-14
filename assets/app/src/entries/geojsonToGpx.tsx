import { mountIsland } from '@/lib/mountIsland';
import { asText, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateGpx } from '@/gps/gpx';
import { parseGeoJson } from '@/gps/geojson';

mountIsland('geojson-to-gpx-root', (_props) => (
    <SingleFileConverterTool
        accept=".geojson,.json"
        readAs="text"
        parse={(content) => parseGeoJson(asText(content))}
        generate={generateGpx}
        outputFileName={(name) => `${name.replace(/\.(geojson|json)$/i, '')}.gpx`}
        outputMimeType="application/gpx+xml"
        i18nPrefix="tools.geojson_to_gpx"
    />
));
