<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

/**
 * Préférences d'évitement — mappées vers `routeModifiers.avoidHighways/avoidTolls/avoidFerries`
 * sur l'API Google Routes. Ce sont des préférences, jamais des garanties : Google peut renvoyer
 * un itinéraire qui les emprunte si aucune alternative raisonnable n'existe. Voir les libellés
 * exacts dans translations/messages.*.yaml et assets/app/src/i18n — ne jamais promettre
 * "guarantees no highways" côté UI.
 */
final readonly class RouteModifiers
{
    public function __construct(
        public bool $avoidHighways = false,
        public bool $avoidTolls = false,
        public bool $avoidFerries = false,
    ) {
    }

    public function isDefault(): bool
    {
        return !$this->avoidHighways && !$this->avoidTolls && !$this->avoidFerries;
    }
}
