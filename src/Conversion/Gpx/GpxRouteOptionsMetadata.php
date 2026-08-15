<?php

declare(strict_types=1);

namespace App\Conversion\Gpx;

/**
 * Métadonnées SmartGPX embarquées dans le bloc &lt;extensions&gt; du GPX généré — un élément
 * schema-légal en GPX 1.1, prévu précisément pour ce genre d'extension propriétaire (voir
 * documentation/technique/routing-options.md). Ne doit jamais rendre le document invalide : si
 * cette valeur est absente, GpxGenerator omet simplement le bloc &lt;extensions&gt;.
 */
final readonly class GpxRouteOptionsMetadata
{
    public function __construct(
        public string $travelMode,
        public bool $avoidHighways,
        public bool $avoidTolls,
        public bool $avoidFerries,
        public string $routingPreference,
        public string $costTier,
    ) {
    }
}
