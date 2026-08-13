<?php

declare(strict_types=1);

namespace App\Routing\Exception;

/**
 * Le fournisseur n'a pas pu être contacté ou a renvoyé une réponse inexploitable (timeout,
 * erreur réseau, statut non-2xx, JSON malformé). Problème de notre côté ou du fournisseur, pas
 * de l'utilisateur — le message affiché doit rester générique, jamais l'erreur brute.
 */
final class RoutingProviderUnavailableException extends RoutingProviderException
{
}
