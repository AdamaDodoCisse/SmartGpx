import { mountIsland } from '@/lib/mountIsland';
import { GpxViewerTool } from '@/components/tools/GpxViewerTool';

mountIsland('gpx-viewer-root', (_props) => <GpxViewerTool />);
