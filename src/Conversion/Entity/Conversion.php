<?php

declare(strict_types=1);

namespace App\Conversion\Entity;

use App\Conversion\Gpx\GpxRouteData;
use App\Conversion\Gpx\GpxRouteOptionsMetadata;
use App\Conversion\Gpx\GpxTrackPoint;
use App\Conversion\Gpx\GpxWaypoint;
use App\Conversion\Parser\ParsedGoogleMapsUrl;
use App\Conversion\Repository\ConversionRepository;
use App\Identity\Entity\User;
use App\Routing\Enum\RoutingFeatureCostTier;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Result\RoutePoint;
use App\Routing\Result\RouteResult;
use App\Routing\ValueObject\RouteLocation;
use App\Routing\ValueObject\RouteOptions;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

/**
 * Enregistrement d'historique/affichage d'une conversion Google Maps → GPX réussie. Le GPX
 * lui-même n'est pas stocké : il est régénéré à la demande depuis waypoints/geometry (voir
 * toGpxRouteData()) — source de vérité unique, régénération triviale, pas de coût.
 *
 * Seules les conversions réussies sont enregistrées en Phase 2 (voir
 * documentation/decisions/ADR-002-credit-ledger.md).
 */
#[ORM\Entity(repositoryClass: ConversionRepository::class)]
#[ORM\Table(name: 'conversion')]
#[ORM\UniqueConstraint(name: 'uniq_conversion_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_conversion_user', columns: ['user_id'])]
class Conversion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private UuidV7 $publicId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 2048)]
    private string $sourceUrl;

    #[ORM\Column(length: 500)]
    private string $originLabel;

    #[ORM\Column(length: 500)]
    private string $destinationLabel;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $stops;

    /** @var list<array{lat: float, lon: float, name: string, type: string}> */
    #[ORM\Column(type: 'json')]
    private array $waypoints;

    /** @var list<array{lat: float, lon: float}> */
    #[ORM\Column(type: 'json')]
    private array $geometry;

    #[ORM\Column(length: 20, enumType: TravelMode::class)]
    private TravelMode $travelMode;

    #[ORM\Column]
    private bool $travelModeInferred;

    #[ORM\Column]
    private int $distanceMeters;

    #[ORM\Column]
    private int $durationSeconds;

    #[ORM\Column]
    private int $trackPointCount;

    /** @var array{routingPreference: string, avoidHighways: bool, avoidTolls: bool, avoidFerries: bool, optimizeWaypointOrder: bool, routeDetail: string} */
    #[ORM\Column(type: 'json')]
    private array $routeOptions;

    #[ORM\Column(length: 20, enumType: RoutingFeatureCostTier::class)]
    private RoutingFeatureCostTier $costTier;

    /** @var array{currencyCode: string, amount: float}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tollEstimate = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $routeLabel = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $waypointTypes;

    /** @var list<int>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $optimizedWaypointOrder = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
        $this->publicId = new UuidV7();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param list<WaypointType> $waypointTypes une entrée par étape intermédiaire, dans l'ordre — les positions manquantes valent STOP
     */
    public static function fromRoute(
        User $user,
        string $sourceUrl,
        ParsedGoogleMapsUrl $parsed,
        RouteResult $route,
        RouteOptions $options,
        RoutingFeatureCostTier $costTier,
        array $waypointTypes = [],
    ): self {
        $conversion = new self();
        $conversion->user = $user;
        $conversion->sourceUrl = $sourceUrl;
        $conversion->originLabel = $parsed->origin->label();
        $conversion->destinationLabel = $parsed->destination->label();
        $conversion->stops = array_map(
            static fn (RouteLocation $location): string => $location->label(),
            $parsed->intermediates,
        );
        $conversion->travelMode = $route->travelMode;
        $conversion->travelModeInferred = $parsed->travelModeInferred;
        $conversion->distanceMeters = $route->distanceMeters;
        $conversion->durationSeconds = $route->durationSeconds;
        $conversion->trackPointCount = \count($route->points);
        $conversion->geometry = array_map(
            static fn (RoutePoint $point): array => ['lat' => $point->latitude, 'lon' => $point->longitude],
            $route->points,
        );
        $conversion->waypoints = self::buildWaypoints($parsed, $route);
        $conversion->routeOptions = [
            'routingPreference' => $options->routingPreference->value,
            'avoidHighways' => $options->modifiers->avoidHighways,
            'avoidTolls' => $options->modifiers->avoidTolls,
            'avoidFerries' => $options->modifiers->avoidFerries,
            'optimizeWaypointOrder' => $options->optimizeWaypointOrder,
            'routeDetail' => $options->routeDetail->value,
        ];
        $conversion->costTier = $costTier;
        $conversion->tollEstimate = null !== $route->tollEstimate
            ? ['currencyCode' => $route->tollEstimate->currencyCode, 'amount' => $route->tollEstimate->amount]
            : null;
        $conversion->routeLabel = $route->routeLabel;
        $conversion->waypointTypes = array_map(
            static fn (int $position) => ($waypointTypes[$position] ?? WaypointType::STOP)->value,
            array_keys($parsed->intermediates),
        );
        $conversion->optimizedWaypointOrder = $route->optimizedWaypointOrder;

        return $conversion;
    }

    /**
     * Les coordonnées de chaque waypoint proviennent des points de début/fin de segment résolus
     * par le fournisseur de routing (jamais ressaisies), y compris pour une Address d'origine —
     * un <wpt> a toujours des coordonnées réelles, jamais géocodées une seconde fois.
     *
     * @return list<array{lat: float, lon: float, name: string, type: string}>
     */
    private static function buildWaypoints(ParsedGoogleMapsUrl $parsed, RouteResult $route): array
    {
        $labels = [$parsed->origin->label()];

        foreach ($parsed->intermediates as $intermediate) {
            $labels[] = $intermediate->label();
        }

        $labels[] = $parsed->destination->label();

        $points = [];

        if ([] !== $route->legs) {
            $points[] = $route->legs[0]->startPoint;

            foreach ($route->legs as $leg) {
                $points[] = $leg->endPoint;
            }
        }

        $waypoints = [];
        $count = min(\count($labels), \count($points));

        for ($i = 0; $i < $count; ++$i) {
            $type = match (true) {
                0 === $i => 'origin',
                $i === $count - 1 => 'destination',
                default => 'stop',
            };

            $waypoints[] = [
                'lat' => $points[$i]->latitude,
                'lon' => $points[$i]->longitude,
                'name' => $labels[$i],
                'type' => $type,
            ];
        }

        return $waypoints;
    }

    public function toGpxRouteData(): GpxRouteData
    {
        return new GpxRouteData(
            routeName: sprintf('%s to %s', $this->originLabel, $this->destinationLabel),
            waypoints: array_map(
                static fn (array $waypoint): GpxWaypoint => new GpxWaypoint(
                    $waypoint['lat'],
                    $waypoint['lon'],
                    $waypoint['name'],
                    $waypoint['type'],
                ),
                $this->waypoints,
            ),
            trackPoints: array_map(
                static fn (array $point): GpxTrackPoint => new GpxTrackPoint($point['lat'], $point['lon']),
                $this->geometry,
            ),
            routeOptions: new GpxRouteOptionsMetadata(
                travelMode: $this->travelMode->value,
                avoidHighways: $this->routeOptions['avoidHighways'],
                avoidTolls: $this->routeOptions['avoidTolls'],
                avoidFerries: $this->routeOptions['avoidFerries'],
                routingPreference: $this->routeOptions['routingPreference'],
                costTier: $this->costTier->value,
            ),
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): UuidV7
    {
        return $this->publicId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getOriginLabel(): string
    {
        return $this->originLabel;
    }

    public function getDestinationLabel(): string
    {
        return $this->destinationLabel;
    }

    /**
     * @return list<string>
     */
    public function getStops(): array
    {
        return $this->stops;
    }

    public function getTravelMode(): TravelMode
    {
        return $this->travelMode;
    }

    public function isTravelModeInferred(): bool
    {
        return $this->travelModeInferred;
    }

    public function getDistanceMeters(): int
    {
        return $this->distanceMeters;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function getTrackPointCount(): int
    {
        return $this->trackPointCount;
    }

    /**
     * @return array{routingPreference: string, avoidHighways: bool, avoidTolls: bool, avoidFerries: bool, optimizeWaypointOrder: bool, routeDetail: string}
     */
    public function getRouteOptions(): array
    {
        return $this->routeOptions;
    }

    public function getCostTier(): RoutingFeatureCostTier
    {
        return $this->costTier;
    }

    /**
     * @return array{currencyCode: string, amount: float}|null
     */
    public function getTollEstimate(): ?array
    {
        return $this->tollEstimate;
    }

    public function getRouteLabel(): ?string
    {
        return $this->routeLabel;
    }

    /**
     * @return list<string>
     */
    public function getWaypointTypes(): array
    {
        return $this->waypointTypes;
    }

    /**
     * @return list<int>|null
     */
    public function getOptimizedWaypointOrder(): ?array
    {
        return $this->optimizedWaypointOrder;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
