/**
 * Types partagés du domaine routing côté frontend — miroir des enums/VO PHP sous src/Routing/
 * (voir documentation/technique/routing-options.md). TWO_WHEELER et TRANSIT sont inclus même si
 * le panneau d'options avancées ne montre que 4 modes par défaut : TRANSIT existait déjà avant
 * les options avancées et ne doit pas régresser.
 */
export type TravelMode = 'DRIVE' | 'TWO_WHEELER' | 'BICYCLE' | 'WALK' | 'TRANSIT';

export type RoutingPreference = 'TRAFFIC_UNAWARE' | 'TRAFFIC_AWARE' | 'TRAFFIC_AWARE_OPTIMAL';

export type RouteDetail = 'STANDARD' | 'HIGH_QUALITY';

export type WaypointType = 'STOP' | 'VIA';

export type RoutePreset = 'FASTEST' | 'ROAD_TRIP' | 'MOTORCYCLE' | 'CUSTOM';

export type VehicleEmissionType = 'GASOLINE' | 'DIESEL' | 'ELECTRIC' | 'HYBRID';

export interface RoutingProviderCapabilities {
    supportedTravelModes: TravelMode[];
    avoidHighways: boolean;
    avoidTolls: boolean;
    avoidFerries: boolean;
    trafficAware: boolean;
    trafficAwareOptimal: boolean;
    waypointOptimization: boolean;
    alternativeRoutes: boolean;
    fuelEfficientRoute: boolean;
    tollEstimation: boolean;
    maxIntermediateWaypoints: number;
}

export interface RouteOptionsState {
    travelMode: TravelMode;
    avoidHighways: boolean;
    avoidTolls: boolean;
    avoidFerries: boolean;
    routingPreference: RoutingPreference;
    optimizeWaypointOrder: boolean;
    routeDetail: RouteDetail;
    showAlternativeRoutes: boolean;
    showFuelEfficientRoute: boolean;
    showTollEstimates: boolean;
    vehicleEmissionType: VehicleEmissionType | null;
    preset: RoutePreset;
}

export const DEFAULT_ROUTE_OPTIONS: RouteOptionsState = {
    travelMode: 'DRIVE',
    avoidHighways: false,
    avoidTolls: false,
    avoidFerries: false,
    routingPreference: 'TRAFFIC_UNAWARE',
    optimizeWaypointOrder: false,
    routeDetail: 'STANDARD',
    showAlternativeRoutes: false,
    showFuelEfficientRoute: false,
    showTollEstimates: false,
    vehicleEmissionType: null,
    preset: 'FASTEST',
};

/**
 * Résolution des presets FASTEST/ROAD_TRIP/MOTORCYCLE — le backend
 * (RoutePresetOptionsResolver) reste la source de vérité pour ce qui est réellement envoyé à
 * Google ; ce mapping local ne sert qu'à refléter l'état visuel du panneau immédiatement, sans
 * attendre une réponse serveur. Garder synchronisé avec RoutePresetOptionsResolver si l'un des
 * deux change.
 */
export const PRESET_OPTIONS: Record<Exclude<RoutePreset, 'CUSTOM'>, Partial<RouteOptionsState>> = {
    FASTEST: { travelMode: 'DRIVE', avoidHighways: false, avoidTolls: false, avoidFerries: false, routeDetail: 'STANDARD' },
    ROAD_TRIP: { travelMode: 'DRIVE', routeDetail: 'HIGH_QUALITY' },
    MOTORCYCLE: { travelMode: 'TWO_WHEELER', avoidHighways: true },
};

export interface ParsedWaypoint {
    label: string;
    index: number;
    type: WaypointType;
}

export interface RouteCandidate {
    index: number;
    routeLabel: string | null;
    distanceMeters: number;
    durationSeconds: number;
    avoidsHighways: boolean;
    avoidsTolls: boolean;
    tollEstimate: { currencyCode: string; amount: number } | null;
}
