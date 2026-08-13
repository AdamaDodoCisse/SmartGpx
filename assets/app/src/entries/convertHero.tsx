import { mountIsland } from '@/lib/mountIsland';
import { ConvertHero } from '@/components/conversion/ConvertHero';

mountIsland('convert-hero-root', (props) => (
    <ConvertHero
        isAuthenticated={'1' === props.isAuthenticated}
        csrfToken={props.csrfToken ?? ''}
        creditBalance={Number.parseInt(props.creditBalance ?? '0', 10)}
    />
));
