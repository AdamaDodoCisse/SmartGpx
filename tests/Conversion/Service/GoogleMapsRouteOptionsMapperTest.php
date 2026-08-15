<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Service;

use App\Conversion\Request\ConvertGoogleMapsUrlRequest;
use App\Conversion\Service\GoogleMapsRouteOptionsMapper;
use App\Routing\Enum\RouteDetail;
use App\Routing\Enum\RoutingPreference;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Result\RoutingProviderCapabilities;
use App\Routing\Service\RoutePresetOptionsResolver;
use PHPUnit\Framework\TestCase;

final class GoogleMapsRouteOptionsMapperTest extends TestCase
{
    private GoogleMapsRouteOptionsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GoogleMapsRouteOptionsMapper(new RoutePresetOptionsResolver());
    }

    public function testARequestWithNoOptionalFieldsMapsToDefaultRouteOptions(): void
    {
        $mapping = $this->mapper->map(new ConvertGoogleMapsUrlRequest(), self::fullCapabilities());

        self::assertSame(RoutingPreference::TRAFFIC_UNAWARE, $mapping->options->routingPreference);
        self::assertFalse($mapping->options->modifiers->avoidHighways);
        self::assertFalse($mapping->options->optimizeWaypointOrder);
        self::assertSame(RouteDetail::STANDARD, $mapping->options->routeDetail);
        self::assertFalse($mapping->options->computeAlternativeRoutes);
        self::assertNull($mapping->presetSuggestedTravelMode);
        self::assertSame([], $mapping->waypointTypes);
    }

    public function testAPresetSuppliesABaseThatExplicitFieldsOverride(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->preset = 'motorcycle';
        $request->avoidHighways = false; // MOTORCYCLE preset sets this true — explicit false overrides it

        $mapping = $this->mapper->map($request, self::fullCapabilities());

        self::assertSame(TravelMode::TWO_WHEELER, $mapping->presetSuggestedTravelMode);
        self::assertFalse($mapping->options->modifiers->avoidHighways, 'An explicit field in the same request must override the preset value.');
    }

    public function testAnUnknownPresetNameIsIgnored(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->preset = 'not-a-real-preset';

        $mapping = $this->mapper->map($request, self::fullCapabilities());

        self::assertNull($mapping->presetSuggestedTravelMode);
        self::assertFalse($mapping->options->modifiers->avoidHighways);
    }

    public function testUnsupportedOptionsAreSilentlyDroppedNeverAnError(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->avoidHighways = true;
        $request->showAlternativeRoutes = true;
        $request->optimizeWaypointOrder = true;

        $mapping = $this->mapper->map($request, self::noAdvancedFeatureCapabilities());

        self::assertFalse($mapping->options->modifiers->avoidHighways, 'avoidHighways requested but not supported by capabilities.');
        self::assertFalse($mapping->options->computeAlternativeRoutes);
        self::assertFalse($mapping->options->optimizeWaypointOrder);
    }

    public function testRoutingPreferenceDowngradesToTheBestSupportedTier(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->routingPreference = 'TRAFFIC_AWARE_OPTIMAL';

        $onlyTrafficAware = new RoutingProviderCapabilities(
            supportedTravelModes: [TravelMode::DRIVE],
            avoidHighways: true,
            avoidTolls: true,
            avoidFerries: true,
            trafficAware: true,
            trafficAwareOptimal: false,
            waypointOptimization: true,
            alternativeRoutes: true,
            fuelEfficientRoute: true,
            tollEstimation: true,
            maxIntermediateWaypoints: 25,
        );

        $mapping = $this->mapper->map($request, $onlyTrafficAware);

        self::assertSame(RoutingPreference::TRAFFIC_AWARE, $mapping->options->routingPreference);
    }

    public function testRoutingPreferenceFallsBackToUnawareWhenNoTrafficSupportAtAll(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->routingPreference = 'TRAFFIC_AWARE_OPTIMAL';

        $mapping = $this->mapper->map($request, self::noAdvancedFeatureCapabilities());

        self::assertSame(RoutingPreference::TRAFFIC_UNAWARE, $mapping->options->routingPreference);
    }

    public function testVehicleProfileIsOnlyBuiltWhenTollEstimatesAreRequestedAndSupported(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->showTollEstimates = true;
        $request->vehicleEmissionType = 'electric';

        $mapping = $this->mapper->map($request, self::fullCapabilities());

        self::assertTrue($mapping->options->showTollEstimates);
        self::assertNotNull($mapping->options->vehicleProfile);
        self::assertSame('ELECTRIC', $mapping->options->vehicleProfile->emissionType->value);
    }

    public function testVehicleProfileIsNullWhenTollEstimatesAreNotSupported(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->showTollEstimates = true;
        $request->vehicleEmissionType = 'electric';

        $mapping = $this->mapper->map($request, self::noAdvancedFeatureCapabilities());

        self::assertFalse($mapping->options->showTollEstimates);
        self::assertNull($mapping->options->vehicleProfile);
    }

    public function testWaypointTypesAreMappedInOrderWithInvalidValuesDefaultingToStop(): void
    {
        $request = new ConvertGoogleMapsUrlRequest();
        $request->waypointTypes = ['VIA', 'not-a-type', 'stop'];

        $mapping = $this->mapper->map($request, self::fullCapabilities());

        self::assertSame([WaypointType::VIA, WaypointType::STOP, WaypointType::STOP], $mapping->waypointTypes);
    }

    private static function fullCapabilities(): RoutingProviderCapabilities
    {
        return new RoutingProviderCapabilities(
            supportedTravelModes: [TravelMode::DRIVE, TravelMode::TWO_WHEELER, TravelMode::BICYCLE, TravelMode::WALK, TravelMode::TRANSIT],
            avoidHighways: true,
            avoidTolls: true,
            avoidFerries: true,
            trafficAware: true,
            trafficAwareOptimal: true,
            waypointOptimization: true,
            alternativeRoutes: true,
            fuelEfficientRoute: true,
            tollEstimation: true,
            maxIntermediateWaypoints: 25,
        );
    }

    private static function noAdvancedFeatureCapabilities(): RoutingProviderCapabilities
    {
        return new RoutingProviderCapabilities(
            supportedTravelModes: [TravelMode::DRIVE],
            avoidHighways: false,
            avoidTolls: false,
            avoidFerries: false,
            trafficAware: false,
            trafficAwareOptimal: false,
            waypointOptimization: false,
            alternativeRoutes: false,
            fuelEfficientRoute: false,
            tollEstimation: false,
            maxIntermediateWaypoints: 25,
        );
    }
}
