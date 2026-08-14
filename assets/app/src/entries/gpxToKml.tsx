import { mountIsland } from '@/lib/mountIsland';
import { SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateKml } from '@/gps/kml';
import { parseGpx } from '@/gps/gpx';

mountIsland('gpx-to-kml-root', (_props) => (
    <SingleFileConverterTool
        accept=".gpx"
        parse={parseGpx}
        generate={generateKml}
        outputFileName={(name) => `${name.replace(/\.gpx$/i, '')}.kml`}
        outputMimeType="application/vnd.google-earth.kml+xml"
        i18nPrefix="tools.gpx_to_kml"
    />
));
