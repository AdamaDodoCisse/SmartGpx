<?php

declare(strict_types=1);

namespace App\Tests\Routing\Service;

use App\Routing\Enum\RoutingFeatureCostTier;
use App\Routing\Service\RouteOptionsCostClassifier;
use App\Routing\ValueObject\RouteModifiers;
use App\Routing\ValueObject\RouteOptions;
use PHPUnit\Framework\TestCase;

final class RouteOptionsCostClassifierTest extends TestCase
{
    private RouteOptionsCostClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new RouteOptionsCostClassifier();
    }

    public function testDefaultOptionsAreStandard(): void
    {
        self::assertSame(RoutingFeatureCostTier::STANDARD, $this->classifier->classify(new RouteOptions()));
    }

    public function testAvoidModifiersAloneStayStandard(): void
    {
        $options = new RouteOptions(modifiers: new RouteModifiers(avoidHighways: true, avoidTolls: true, avoidFerries: true));

        self::assertSame(RoutingFeatureCostTier::STANDARD, $this->classifier->classify($options));
    }

    public function testWaypointOptimizationAloneStaysStandard(): void
    {
        self::assertSame(
            RoutingFeatureCostTier::STANDARD,
            $this->classifier->classify(new RouteOptions(optimizeWaypointOrder: true)),
        );
    }

    public function testAlternativeRoutesAreAdvanced(): void
    {
        self::assertSame(
            RoutingFeatureCostTier::ADVANCED,
            $this->classifier->classify(new RouteOptions(computeAlternativeRoutes: true)),
        );
    }

    public function testFuelEfficientRouteIsAdvanced(): void
    {
        self::assertSame(
            RoutingFeatureCostTier::ADVANCED,
            $this->classifier->classify(new RouteOptions(showFuelEfficientRoute: true)),
        );
    }

    public function testTollEstimatesAreAdvanced(): void
    {
        self::assertSame(
            RoutingFeatureCostTier::ADVANCED,
            $this->classifier->classify(new RouteOptions(showTollEstimates: true)),
        );
    }
}
