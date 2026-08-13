<?php

declare(strict_types=1);

namespace App\Conversion\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class ConvertGoogleMapsUrlRequest
{
    #[Assert\NotBlank]
    #[Assert\Url]
    public string $url = '';

    /**
     * Sélecteur de mode de transport explicite depuis l'UI — prime toujours sur un mode déduit
     * de l'URL (voir GoogleMapsUrlParser, format "chemin" où le mode n'est jamais fiable).
     */
    public ?string $travelMode = null;
}
