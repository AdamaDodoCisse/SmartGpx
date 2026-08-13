import { mountIsland } from '@/lib/mountIsland';
import { ExtensionConnect } from '@/components/extension/ExtensionConnect';

mountIsland('extension-connect-root', (props) => (
    <ExtensionConnect
        token={props.token ?? ''}
        extensionId={props.extensionId ?? ''}
        apiOrigin={props.apiOrigin ?? ''}
    />
));
