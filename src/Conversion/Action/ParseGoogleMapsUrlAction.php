<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsUrlParser;
use App\Conversion\Parser\ParsedGoogleMapsUrl;

/**
 * Extrait origine/destination/étapes d'un lien Google Maps sans jamais appeler la route de calcul
 * d'itinéraire payante — GoogleMapsUrlParser lui-même ne contacte Google que pour résoudre un
 * lien court (voir GoogleMapsShortLinkResolver), pas pour calculer un itinéraire. Utilisée par le
 * panneau d'options avancées pour peupler la liste STOP/VIA avant toute conversion, gratuitement.
 */
final class ParseGoogleMapsUrlAction
{
    public function __construct(
        private readonly GoogleMapsUrlParser $urlParser,
    ) {
    }

    /**
     * @throws InvalidGoogleMapsUrlException
     * @throws UnsupportedGoogleMapsUrlException
     */
    public function execute(string $rawUrl): ParsedGoogleMapsUrl
    {
        return $this->urlParser->parse($rawUrl);
    }
}
