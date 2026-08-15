<?php

declare(strict_types=1);

namespace App\Conversion\Gpx;

/**
 * Génère un GPX 1.1 valide via DOMDocument (pas de bibliothèque tierce — voir
 * documentation/technique/google-maps-to-gpx.md). Pas d'élément <ele> : l'API Google Routes v2
 * ne renvoie pas l'altitude dans la forme de réponse utilisée ici — omission valide au regard du
 * schéma GPX 1.1 (élément optionnel).
 *
 * <extensions> (sous <metadata>, quand GpxRouteData::$routeOptions est fourni) porte les options
 * de routage appliquées dans un espace de noms SmartGPX dédié — un élément schema-légal en GPX
 * 1.1, prévu pour ce genre d'extension propriétaire (voir
 * documentation/technique/routing-options.md). Absent si $routeOptions est null : le document
 * reste valide dans les deux cas.
 */
final class GpxGenerator
{
    private const string GPX_NAMESPACE = 'http://www.topografix.com/GPX/1/1';
    private const string SMARTGPX_NAMESPACE = 'https://smartgpx.app/gpx/extensions/1';

    public function generate(GpxRouteData $route): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $gpx = $this->createElement($document, 'gpx');
        $gpx->setAttribute('version', '1.1');
        $gpx->setAttribute('creator', 'SmartGPX');
        $gpx->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $gpx->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation',
            self::GPX_NAMESPACE.' '.self::GPX_NAMESPACE.'/gpx.xsd',
        );
        $document->appendChild($gpx);

        $gpx->appendChild($this->buildMetadata($document, $route->routeName, $route->routeOptions));

        foreach ($route->waypoints as $waypoint) {
            $gpx->appendChild($this->buildWaypoint($document, $waypoint));
        }

        $gpx->appendChild($this->buildTrack($document, $route));

        $xml = $document->saveXML();

        if (false === $xml) {
            throw new \RuntimeException('Failed to generate GPX XML.');
        }

        return $xml;
    }

    private function buildMetadata(\DOMDocument $document, string $routeName, ?GpxRouteOptionsMetadata $routeOptions): \DOMElement
    {
        $metadata = $this->createElement($document, 'metadata');
        $metadata->appendChild($this->createTextElement($document, 'name', $routeName));
        $metadata->appendChild($this->createTextElement(
            $document,
            'time',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
        ));

        if (null !== $routeOptions) {
            $metadata->appendChild($this->buildExtensions($document, $routeOptions));
        }

        return $metadata;
    }

    private function buildExtensions(\DOMDocument $document, GpxRouteOptionsMetadata $routeOptions): \DOMElement
    {
        $extensions = $this->createElement($document, 'extensions');

        $routeOptionsElement = $document->createElementNS(self::SMARTGPX_NAMESPACE, 'smartgpx:routeOptions');

        if (false === $routeOptionsElement) {
            throw new \RuntimeException('Failed to create GPX extensions element.');
        }

        $routeOptionsElement->setAttribute('travelMode', $routeOptions->travelMode);
        $routeOptionsElement->setAttribute('avoidHighways', $routeOptions->avoidHighways ? 'true' : 'false');
        $routeOptionsElement->setAttribute('avoidTolls', $routeOptions->avoidTolls ? 'true' : 'false');
        $routeOptionsElement->setAttribute('avoidFerries', $routeOptions->avoidFerries ? 'true' : 'false');
        $routeOptionsElement->setAttribute('routingPreference', $routeOptions->routingPreference);
        $routeOptionsElement->setAttribute('costTier', $routeOptions->costTier);

        $extensions->appendChild($routeOptionsElement);

        return $extensions;
    }

    private function buildWaypoint(\DOMDocument $document, GpxWaypoint $waypoint): \DOMElement
    {
        $wpt = $this->createElement($document, 'wpt');
        $wpt->setAttribute('lat', (string) $waypoint->latitude);
        $wpt->setAttribute('lon', (string) $waypoint->longitude);
        $wpt->appendChild($this->createTextElement($document, 'name', $waypoint->name));
        $wpt->appendChild($this->createTextElement($document, 'type', $waypoint->type));

        return $wpt;
    }

    private function buildTrack(\DOMDocument $document, GpxRouteData $route): \DOMElement
    {
        $trk = $this->createElement($document, 'trk');
        $trk->appendChild($this->createTextElement($document, 'name', $route->routeName));

        $trkseg = $this->createElement($document, 'trkseg');

        foreach ($route->trackPoints as $point) {
            $trkpt = $this->createElement($document, 'trkpt');
            $trkpt->setAttribute('lat', (string) $point->latitude);
            $trkpt->setAttribute('lon', (string) $point->longitude);
            $trkseg->appendChild($trkpt);
        }

        $trk->appendChild($trkseg);

        return $trk;
    }

    private function createTextElement(\DOMDocument $document, string $name, string $text): \DOMElement
    {
        $element = $this->createElement($document, $name);
        $element->appendChild($document->createTextNode($text));

        return $element;
    }

    /**
     * Tous les éléments sont créés via createElementNS avec le même URI, y compris les enfants :
     * mélanger createElement() (sans espace de noms) sous un parent créé avec createElementNS
     * produirait un xmlns="" parasite sur les enfants lors de la sérialisation.
     */
    private function createElement(\DOMDocument $document, string $name): \DOMElement
    {
        $element = $document->createElementNS(self::GPX_NAMESPACE, $name);

        if (false === $element) {
            throw new \RuntimeException(sprintf('Failed to create GPX element "%s".', $name));
        }

        return $element;
    }
}
