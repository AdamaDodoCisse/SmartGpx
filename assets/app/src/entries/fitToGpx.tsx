import { mountIsland } from '@/lib/mountIsland';
import { asArrayBuffer, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateGpx } from '@/gps/gpx';
import { parseFit } from '@/gps/fit';

mountIsland('fit-to-gpx-root', (_props) => (
    <SingleFileConverterTool
        accept=".fit"
        readAs="arrayBuffer"
        parse={(content) => parseFit(asArrayBuffer(content))}
        generate={generateGpx}
        outputFileName={(name) => `${name.replace(/\.fit$/i, '')}.gpx`}
        outputMimeType="application/gpx+xml"
        i18nPrefix="tools.fit_to_gpx"
    />
));
