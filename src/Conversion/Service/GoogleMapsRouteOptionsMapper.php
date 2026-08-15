<?php

declare(strict_types=1);

namespace App\Conversion\Service;

use App\Conversion\Request\ConvertGoogleMapsUrlRequest;
use App\Conversion\ValueObject\GoogleMapsRouteOptionsMapping;
use App\Routing\Enum\RouteDetail;
use App\Routing\Enum\RoutePreset;
use App\Routing\Enum\RoutingPreference;
use App\Routing\Enum\VehicleEmissionType;
use App\Routing\Enum\WaypointType;
use App\Routing\Result\RoutingProviderCapabilities;
use App\Routing\Service\RoutePresetOptionsResolver;
use App\Routing\ValueObject\RouteModifiers;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\VehicleProfile;

/**
 * Traduit une requête HTTP en RouteOptions réellement applicables, en filtrant silencieusement
 * toute option que les capabilities du fournisseur actif ne supportent pas — jamais une erreur,
 * conformément à la règle "ne jamais afficher/envoyer une option non supportée". Un preset,
 * lorsque fourni, sert de base ; tout champ explicite de la même requête le surcharge champ par
 * champ (voir RoutePresetOptionsResolver).
 */
final class GoogleMapsRouteOptionsMapper
{
    public function __construct(
        private readonly RoutePresetOptionsResolver $presetResolver,
    ) {
    }

    public function map(ConvertGoogleMapsUrlRequest $request, RoutingProviderCapabilities $capabilities): GoogleMapsRouteOptionsMapping
    {
        $base = new RouteOptions();
        $presetTravelMode = null;

        if (null !== $request->preset) {
            $preset = RoutePreset::tryFrom(strtoupper($request->preset));

            if (null !== $preset) {
                $resolution = $this->presetResolver->resolve($preset);
                $base = $resolution->options;
                $presetTravelMode = $resolution->travelMode;
            }
        }

        $requestedPreference = null !== $request->routingPreference
            ? (RoutingPreference::tryFrom(strtoupper($request->routingPreference)) ?? $base->routingPreference)
            : $base->routingPreference;

        $requestedRouteDetail = null !== $request->routeDetail
            ? (RouteDetail::tryFrom(strtoupper($request->routeDetail)) ?? $base->routeDetail)
            : $base->routeDetail;

        $showTolls = $capabilities->tollEstimation && ($request->showTollEstimates ?? $base->showTollEstimates);
        $vehicleProfile = null;

        if ($showTolls && null !== $request->vehicleEmissionType) {
            $emission = VehicleEmissionType::tryFrom(strtoupper($request->vehicleEmissionType));

            if (null !== $emission) {
                $vehicleProfile = new VehicleProfile($emission);
            }
        }

        $options = new RouteOptions(
            routingPreference: $this->applicableRoutingPreference($requestedPreference, $capabilities),
            modifiers: new RouteModifiers(
                avoidHighways: $capabilities->avoidHighways && ($request->avoidHighways ?? $base->modifiers->avoidHighways),
                avoidTolls: $capabilities->avoidTolls && ($request->avoidTolls ?? $base->modifiers->avoidTolls),
                avoidFerries: $capabilities->avoidFerries && ($request->avoidFerries ?? $base->modifiers->avoidFerries),
            ),
            optimizeWaypointOrder: $capabilities->waypointOptimization && ($request->optimizeWaypointOrder ?? $base->optimizeWaypointOrder),
            routeDetail: $requestedRouteDetail,
            computeAlternativeRoutes: $capabilities->alternativeRoutes && ($request->showAlternativeRoutes ?? $base->computeAlternativeRoutes),
            showFuelEfficientRoute: $capabilities->fuelEfficientRoute && ($request->showFuelEfficientRoute ?? $base->showFuelEfficientRoute),
            showTollEstimates: $showTolls,
            vehicleProfile: $vehicleProfile,
        );

        $waypointTypes = array_map(
            static fn (string $raw): WaypointType => WaypointType::tryFrom(strtoupper($raw)) ?? WaypointType::STOP,
            $request->waypointTypes ?? [],
        );

        return new GoogleMapsRouteOptionsMapping($options, $waypointTypes, $presetTravelMode);
    }

    private function applicableRoutingPreference(RoutingPreference $requested, RoutingProviderCapabilities $capabilities): RoutingPreference
    {
        return match (true) {
            RoutingPreference::TRAFFIC_AWARE_OPTIMAL === $requested && $capabilities->trafficAwareOptimal => $requested,
            RoutingPreference::TRAFFIC_AWARE_OPTIMAL === $requested && $capabilities->trafficAware => RoutingPreference::TRAFFIC_AWARE,
            RoutingPreference::TRAFFIC_AWARE === $requested && $capabilities->trafficAware => $requested,
            default => RoutingPreference::TRAFFIC_UNAWARE,
        };
    }
}
