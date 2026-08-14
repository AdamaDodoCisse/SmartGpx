import { mountIsland } from '@/lib/mountIsland';
import { asText, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateGpx } from '@/gps/gpx';
import { parseTcx } from '@/gps/tcx';

mountIsland('tcx-to-gpx-root', (_props) => (
    <SingleFileConverterTool
        accept=".tcx"
        readAs="text"
        parse={(content) => parseTcx(asText(content))}
        generate={generateGpx}
        outputFileName={(name) => `${name.replace(/\.tcx$/i, '')}.gpx`}
        outputMimeType="application/gpx+xml"
        i18nPrefix="tools.tcx_to_gpx"
    />
));
