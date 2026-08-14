import { mountIsland } from '@/lib/mountIsland';
import { asText, SingleFileConverterTool } from '@/components/tools/SingleFileConverterTool';
import { parseGpx } from '@/gps/gpx';
import { generateFit } from '@/gps/fit';

mountIsland('gpx-to-fit-root', (_props) => (
    <SingleFileConverterTool
        accept=".gpx"
        readAs="text"
        parse={(content) => parseGpx(asText(content))}
        generate={generateFit}
        outputFileName={(name) => `${name.replace(/\.gpx$/i, '')}.fit`}
        outputMimeType="application/vnd.ant.fit"
        i18nPrefix="tools.gpx_to_fit"
    />
));
