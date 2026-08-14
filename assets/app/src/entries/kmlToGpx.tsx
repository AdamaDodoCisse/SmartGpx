import { mountIsland } from '@/lib/mountIsland';
import { SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { generateGpx } from '@/gps/gpx';
import { parseKml } from '@/gps/kml';

mountIsland('kml-to-gpx-root', (_props) => (
    <SingleFileConverterTool
        accept=".kml"
        parse={parseKml}
        generate={generateGpx}
        outputFileName={(name) => `${name.replace(/\.kml$/i, '')}.gpx`}
        outputMimeType="application/gpx+xml"
        i18nPrefix="tools.kml_to_gpx"
    />
));
