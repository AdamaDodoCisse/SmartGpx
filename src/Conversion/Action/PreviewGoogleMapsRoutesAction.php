<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsUrlParser;
use App\Conversion\Parser\ParsedGoogleMapsUrl;
use App\Conversion\Service\RoutePreviewStore;
use App\Conversion\ValueObject\CachedRoutePreview;
use App\Conversion\ValueObject\GoogleMapsRoutePreview;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Exception\RoutingProviderException;
use App\Routing\Provider\RoutingProviderInterface;
use App\Routing\ValueObject\RouteOptions;

/**
 * Calcule des itinéraires candidats (alternatives et/ou route de référence économe en carburant)
 * sans réserver ni consommer de crédit — rien n'a encore été exporté. Le résultat est mis en
 * cache sous un previewId ; voir ExportPreviewedRouteAction pour l'étape qui facture réellement
 * l'itinéraire choisi. N'est appelée que lorsque RouteOptions::requestsMultipleRoutes() est vrai
 * — sinon ConvertGoogleMapsToGpxAction suffit en une seule étape.
 */
final class PreviewGoogleMapsRoutesAction
{
    public function __construct(
        private readonly GoogleMapsUrlParser $urlParser,
        private readonly RoutingProviderInterface $routingProvider,
        private readonly RoutePreviewStore $previewStore,
    ) {
    }

    /**
     * @param list<WaypointType> $waypointTypes
     *
     * @throws EmailNotVerifiedException
     * @throws InvalidGoogleMapsUrlException
     * @throws UnsupportedGoogleMapsUrlException
     * @throws RoutingProviderException
     */
    public function execute(
        User $user,
        string $rawUrl,
        ?TravelMode $travelModeOverride,
        RouteOptions $options,
        array $waypointTypes,
    ): GoogleMapsRoutePreview {
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

        $intermediates = ConvertGoogleMapsToGpxAction::buildRouteWaypoints($parsed->intermediates, $waypointTypes);

        $computation = $this->routingProvider->computeRoutes(
            $parsed->origin,
            $parsed->destination,
            $intermediates,
            $parsed->travelMode,
            $options,
        );

        $previewId = $this->previewStore->store(new CachedRoutePreview(
            userId: $user->getId() ?? throw new \LogicException('An authenticated user must have a persisted id.'),
            sourceUrl: $rawUrl,
            parsed: $parsed,
            computation: $computation,
            options: $options,
            waypointTypes: $waypointTypes,
        ));

        return new GoogleMapsRoutePreview($previewId, $computation);
    }
}
