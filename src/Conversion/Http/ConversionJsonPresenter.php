<?php

declare(strict_types=1);

namespace App\Conversion\Http;

use App\Conversion\Entity\Conversion;

/**
 * Forme JSON partagée entre le contrôleur web (session) et le contrôleur de l'extension Chrome
 * (jeton) — les deux appellent la même ConvertGoogleMapsToGpxAction et ne doivent pas diverger
 * sur la forme de la réponse.
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
     * }
     */
    public function toArray(Conversion $conversion, string $downloadUrl): array
    {
        return [
            'publicId' => (string) $conversion->getPublicId(),
            'origin' => $conversion->getOriginLabel(),
            'destination' => $conversion->getDestinationLabel(),
            'stops' => $conversion->getStops(),
            'distanceMeters' => $conversion->getDistanceMeters(),
            'durationSeconds' => $conversion->getDurationSeconds(),
            'travelMode' => $conversion->getTravelMode()->value,
            'travelModeInferred' => $conversion->isTravelModeInferred(),
            'trackPointCount' => $conversion->getTrackPointCount(),
            'downloadUrl' => $downloadUrl,
        ];
    }
}
