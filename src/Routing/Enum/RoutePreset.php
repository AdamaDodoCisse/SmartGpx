<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Un raccourci UI vers une combinaison de RouteOptions (voir RoutePresetOptionsResolver) —
 * CUSTOM n'a pas de résolution : il signifie que l'utilisateur a modifié manuellement au moins un
 * réglage après avoir choisi un preset, et n'est jamais envoyé au backend (le frontend envoie
 * alors les champs RouteOptions explicites au lieu d'un nom de preset).
 */
enum RoutePreset: string
{
    case FASTEST = 'FASTEST';
    case ROAD_TRIP = 'ROAD_TRIP';
    case MOTORCYCLE = 'MOTORCYCLE';
}
