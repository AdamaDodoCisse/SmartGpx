import { mountIsland } from '@/lib/mountIsland';
import { asText, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { parseGpx } from '@/gps/gpx';
import { generateTcx } from '@/gps/tcx';

mountIsland('gpx-to-tcx-root', (_props) => (
    <SingleFileConverterTool
        accept=".gpx"
        readAs="text"
        parse={(content) => parseGpx(asText(content))}
        generate={generateTcx}
        outputFileName={(name) => `${name.replace(/\.gpx$/i, '')}.tcx`}
        outputMimeType="application/vnd.garmin.tcx+xml"
        i18nPrefix="tools.gpx_to_tcx"
    />
));
