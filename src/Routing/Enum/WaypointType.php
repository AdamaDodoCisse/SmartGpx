<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * STOP : l'utilisateur souhaite réellement s'arrêter à ce waypoint (comportement historique,
 * seul type existant avant les options avancées).
 * VIA : l'itinéraire doit passer par ce point sans que ce soit une étape réelle — mappé vers
 * `via: true` sur le waypoint Google (voir GoogleRoutesProvider).
 */
enum WaypointType: string
{
    case STOP = 'STOP';
    case VIA = 'VIA';
}
