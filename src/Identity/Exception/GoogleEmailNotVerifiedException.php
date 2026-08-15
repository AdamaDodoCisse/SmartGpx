<?php

declare(strict_types=1);

namespace App\Identity\Exception;

/**
 * Google rapporte cette adresse comme non vérifiée (cas rare, comptes Google Workspace) alors
 * qu'un compte local existe déjà pour la même adresse — on ne lie jamais un compte Google sur
 * la seule foi d'une adresse non confirmée par Google lui-même.
 */
final class GoogleEmailNotVerifiedException extends \RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('Google reports email "%s" as unverified; refusing to link.', $email));
    }
}
