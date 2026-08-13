<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

/**
 * Une origine, destination ou étape intermédiaire d'un itinéraire : soit une adresse littérale,
 * soit des coordonnées GPS. Ne jamais envoyer des coordonnées reconnues comme une adresse au
 * fournisseur de routing — voir Coordinates::tryParse().
 */
interface RouteLocation
{
    /**
     * @return array{address: string}|array{location: array{latLng: array{latitude: float, longitude: float}}}
     */
    public function toGoogleWaypoint(): array;

    /**
     * Représentation lisible utilisée pour l'affichage et comme nom de secours dans le GPX.
     */
    public function label(): string;
}
