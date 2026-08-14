import { mountIsland } from '@/lib/mountIsland';
import { asArrayBuffer, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateGpx } from '@/gps/gpx';
import { parseKmz } from '@/gps/kmz';

mountIsland('kmz-to-gpx-root', (_props) => (
    <SingleFileConverterTool
        accept=".kmz"
        readAs="arrayBuffer"
        parse={(content) => parseKmz(asArrayBuffer(content))}
        generate={generateGpx}
        outputFileName={(name) => `${name.replace(/\.kmz$/i, '')}.gpx`}
        outputMimeType="application/gpx+xml"
        i18nPrefix="tools.kmz_to_gpx"
    />
));
