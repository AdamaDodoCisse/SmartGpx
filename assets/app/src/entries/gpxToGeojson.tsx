import { mountIsland } from '@/lib/mountIsland';
import { asText, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { parseGpx } from '@/gps/gpx';
import { generateGeoJson } from '@/gps/geojson';

mountIsland('gpx-to-geojson-root', (_props) => (
    <SingleFileConverterTool
        accept=".gpx"
        readAs="text"
        parse={(content) => parseGpx(asText(content))}
        generate={generateGeoJson}
        outputFileName={(name) => `${name.replace(/\.gpx$/i, '')}.geojson`}
        outputMimeType="application/geo+json"
        i18nPrefix="tools.gpx_to_geojson"
    />
));
