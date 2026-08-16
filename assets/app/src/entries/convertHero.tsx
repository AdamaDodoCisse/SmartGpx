import { mountIsland } from '@/lib/mountIsland';
import { setLandingPage } from '@/lib/attribution';
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
    // Seules les 3 pages du cluster Garmin/Wahoo/OsmAnd passent cet attribut ; la page d'accueil
    // ne le pose jamais, donc une visite homepage n'écrase jamais une attribution déjà posée par
    // un guide — voir documentation/technique/google-tag-manager.md.
    if (props.landingPage) {
        setLandingPage(props.landingPage);
    }

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
