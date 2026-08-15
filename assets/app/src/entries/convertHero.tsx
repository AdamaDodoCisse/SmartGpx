import { mountIsland } from '@/lib/mountIsland';
import { ConvertHero } from '@/components/conversion/ConvertHero';
import type { RoutingProviderCapabilities } from '@/components/conversion/routing/types';

const FALLBACK_CAPABILITIES: RoutingProviderCapabilities = {
    supportedTravelModes: ['DRIVE'],
    avoidHighways: false,
    avoidTolls: false,
    avoidFerries: false,
    trafficAware: false,
    trafficAwareOptimal: false,
    waypointOptimization: false,
    alternativeRoutes: false,
    fuelEfficientRoute: false,
    tollEstimation: false,
    maxIntermediateWaypoints: 0,
};

mountIsland('convert-hero-root', (props) => {
    let capabilities = FALLBACK_CAPABILITIES;

    try {
        capabilities = props.capabilities ? (JSON.parse(props.capabilities) as RoutingProviderCapabilities) : FALLBACK_CAPABILITIES;
    } catch {
        capabilities = FALLBACK_CAPABILITIES;
    }

    return (
        <ConvertHero
            isAuthenticated={'1' === props.isAuthenticated}
            isVerified={'1' === props.isVerified}
            csrfToken={props.csrfToken ?? ''}
            creditBalance={Number.parseInt(props.creditBalance ?? '0', 10)}
            capabilities={capabilities}
        />
    );
});
