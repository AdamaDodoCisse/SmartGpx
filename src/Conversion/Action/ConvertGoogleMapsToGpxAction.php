<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Entity\Conversion;
use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsUrlParser;
use App\Conversion\Parser\ParsedGoogleMapsUrl;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Exception\RoutingProviderException;
use App\Routing\Provider\RoutingProviderInterface;
use App\Routing\ValueObject\RouteLocation;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\RouteWaypoint;
use App\Usage\Action\ConsumeReservedCreditAction;
use App\Usage\Action\ReleaseReservedCreditAction;
use App\Usage\Action\ReserveCreditAction;
use App\Usage\Exception\InsufficientCreditsException;

/**
 * Flux à une étape (comportement historique) : réserver → calculer → consommer. N'est jamais
 * utilisé quand RouteOptions::requestsMultipleRoutes() est vrai — dans ce cas, le contrôleur
 * appelle PreviewGoogleMapsRoutesAction puis ExportPreviewedRouteAction à la place (voir
 * documentation/technique/routing-options.md), pour ne réserver un crédit que sur l'itinéraire
 * réellement exporté. Si appelée malgré tout avec des options qui produisent plusieurs
 * itinéraires, seul le premier (le principal) est utilisé — filet de sécurité, pas le chemin
 * attendu.
 */
final class ConvertGoogleMapsToGpxAction
{
    public function __construct(
        private readonly GoogleMapsUrlParser $urlParser,
        private readonly RoutingProviderInterface $routingProvider,
        private readonly ReserveCreditAction $reserveCreditAction,
        private readonly ConsumeReservedCreditAction $consumeReservedCreditAction,
        private readonly ReleaseReservedCreditAction $releaseReservedCreditAction,
    ) {
    }

    /**
     * @param list<WaypointType> $waypointTypes une entrée par étape intermédiaire, dans l'ordre — les positions manquantes sont traitées comme STOP
     *
     * @throws EmailNotVerifiedException         nothing is charged, checked before anything else
     * @throws InvalidGoogleMapsUrlException     nothing is charged, the URL is not even parseable
     * @throws UnsupportedGoogleMapsUrlException nothing is charged, unsupported link shape
     * @throws InsufficientCreditsException      nothing is charged, nothing external is called
     * @throws RoutingProviderException          a released reservation — 0 credits charged
     */
    public function execute(
        User $user,
        string $rawUrl,
        ?TravelMode $travelModeOverride = null,
        RouteOptions $options = new RouteOptions(),
        array $waypointTypes = [],
    ): Conversion {
        if (!$user->isVerified()) {
            throw new EmailNotVerifiedException($user);
        }

        $parsed = $this->urlParser->parse($rawUrl);

        if (null !== $travelModeOverride) {
            $parsed = new ParsedGoogleMapsUrl(
                $parsed->origin,
                $parsed->destination,
                $parsed->intermediates,
                $travelModeOverride,
                travelModeInferred: false,
            );
        }

        $intermediates = self::buildRouteWaypoints($parsed->intermediates, $waypointTypes);

        $this->reserveCreditAction->execute($user);

        try {
            $computation = $this->routingProvider->computeRoutes(
                $parsed->origin,
                $parsed->destination,
                $intermediates,
                $parsed->travelMode,
                $options,
            );
        } catch (RoutingProviderException $exception) {
            $this->releaseReservedCreditAction->execute($user);

            throw $exception;
        }

        $conversion = Conversion::fromRoute($user, $rawUrl, $parsed, $computation->primary(), $options, $computation->costTier, $waypointTypes);
        $this->consumeReservedCreditAction->execute($user, $conversion);

        return $conversion;
    }

    /**
     * @param list<RouteLocation> $locations
     * @param list<WaypointType>  $waypointTypes
     *
     * @return list<RouteWaypoint>
     */
    public static function buildRouteWaypoints(array $locations, array $waypointTypes): array
    {
        return array_map(
            static fn (int $position, RouteLocation $location): RouteWaypoint => new RouteWaypoint(
                $location,
                $waypointTypes[$position] ?? WaypointType::STOP,
                $position,
            ),
            array_keys($locations),
            $locations,
        );
    }
}
