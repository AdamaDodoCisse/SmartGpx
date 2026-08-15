<?php

declare(strict_types=1);

namespace App\Conversion\Http;

use App\Conversion\Entity\Conversion;

/**
 * Forme JSON partagée entre le contrôleur web (session) et le contrôleur de l'extension Chrome
 * (jeton) — les deux appellent la même ConvertGoogleMapsToGpxAction et ne doivent pas diverger
 * sur la forme de la réponse.
 *
 * routeOptionsApplied/costTier/tollEstimate/routeLabel/stopOrder sont de nouveaux champs
 * (options avancées) — toujours présents mais avec des valeurs par défaut fidèles au
 * comportement historique quand aucune option avancée n'a été demandée, pour ne rien changer
 * côté client existant (extension Chrome).
 */
final class ConversionJsonPresenter
{
    /**
     * @return array{
     *     publicId: string,
     *     origin: string,
     *     destination: string,
     *     stops: list<string>,
     *     distanceMeters: int,
     *     durationSeconds: int,
     *     travelMode: string,
     *     travelModeInferred: bool,
     *     trackPointCount: int,
     *     downloadUrl: string,
     *     routeOptionsApplied: array{routingPreference: string, avoidHighways: bool, avoidTolls: bool, avoidFerries: bool, optimizeWaypointOrder: bool, routeDetail: string},
     *     costTier: string,
     *     tollEstimate: array{currencyCode: string, amount: float}|null,
     *     routeLabel: string|null,
     *     originalStopOrder: list<string>|null,
     *     optimizedStopOrder: list<string>|null,
     * }
     */
    public function toArray(Conversion $conversion, string $downloadUrl): array
    {
        $stops = $conversion->getStops();
        $optimizedOrder = $conversion->getOptimizedWaypointOrder();

        return [
            'publicId' => (string) $conversion->getPublicId(),
            'origin' => $conversion->getOriginLabel(),
            'destination' => $conversion->getDestinationLabel(),
            'stops' => $stops,
            'distanceMeters' => $conversion->getDistanceMeters(),
            'durationSeconds' => $conversion->getDurationSeconds(),
            'travelMode' => $conversion->getTravelMode()->value,
            'travelModeInferred' => $conversion->isTravelModeInferred(),
            'trackPointCount' => $conversion->getTrackPointCount(),
            'downloadUrl' => $downloadUrl,
            'routeOptionsApplied' => $conversion->getRouteOptions(),
            'costTier' => $conversion->getCostTier()->value,
            'tollEstimate' => $conversion->getTollEstimate(),
            'routeLabel' => $conversion->getRouteLabel(),
            'originalStopOrder' => null !== $optimizedOrder ? $stops : null,
            'optimizedStopOrder' => null !== $optimizedOrder
                ? array_map(static fn (int $originalPosition): string => $stops[$originalPosition] ?? '', $optimizedOrder)
                : null,
        ];
    }
}
