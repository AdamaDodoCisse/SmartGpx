<?php

declare(strict_types=1);

namespace App\Extension\Result;

use App\Extension\Entity\ExtensionAuthorization;

/**
 * Porte le jeton en clair, disponible une seule fois au moment de la génération — jamais
 * persisté ni journalisé au-delà de ce DTO éphémère.
 */
final readonly class GeneratedExtensionAuthorization
{
    public function __construct(
        public ExtensionAuthorization $authorization,
        public string $plainToken,
    ) {
    }
}
