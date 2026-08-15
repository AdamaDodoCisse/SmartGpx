<?php

declare(strict_types=1);

namespace App\Routing\Result;

/**
 * Estimation de péage — construite uniquement quand le fournisseur renvoie effectivement une
 * estimation (jamais calculée ou devinée localement). Toujours affichée comme "Estimated tolls",
 * jamais comme un prix garanti — voir translations/messages.*.yaml et
 * assets/app/src/i18n (clés convert.advanced.output.toll_*).
 */
final readonly class RouteTollEstimate
{
    public function __construct(
        public string $currencyCode,
        public float $amount,
    ) {
    }
}
