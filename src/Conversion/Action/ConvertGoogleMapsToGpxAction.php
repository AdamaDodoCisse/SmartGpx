<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Entity\Conversion;
use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsUrlParser;
use App\Conversion\Parser\ParsedGoogleMapsUrl;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RoutingProviderException;
use App\Routing\Provider\RoutingProviderInterface;
use App\Usage\Action\ConsumeReservedCreditAction;
use App\Usage\Action\ReleaseReservedCreditAction;
use App\Usage\Action\ReserveCreditAction;
use App\Usage\Exception\InsufficientCreditsException;

final class ConvertGoogleMapsToGpxAction
{
    public function __construct(
        private readonly GoogleMapsUrlParser $urlParser,
        private readonly RoutingProviderInterface $routingProvider,
        private readonly ReserveCreditAction $reserveCreditAction,
        private readonly ConsumeReservedCreditAction $consumeReservedCreditAction,
        private readonly ReleaseReservedCreditAction $releaseReservedCreditAction,
    ) {
    }

    /**
     * @throws EmailNotVerifiedException         nothing is charged, checked before anything else
     * @throws InvalidGoogleMapsUrlException     nothing is charged, the URL is not even parseable
     * @throws UnsupportedGoogleMapsUrlException nothing is charged, unsupported link shape
     * @throws InsufficientCreditsException      nothing is charged, nothing external is called
     * @throws RoutingProviderException          a released reservation — 0 credits charged
     */
    public function execute(User $user, string $rawUrl, ?TravelMode $travelModeOverride = null): Conversion
    {
        if (!$user->isVerified()) {
            throw new EmailNotVerifiedException($user);
        }

        $parsed = $this->urlParser->parse($rawUrl);

        if (null !== $travelModeOverride) {
            $parsed = new ParsedGoogleMapsUrl(
                $parsed->origin,
                $parsed->destination,
                $parsed->intermediates,
                $travelModeOverride,
                travelModeInferred: false,
            );
        }

        $this->reserveCreditAction->execute($user);

        try {
            $route = $this->routingProvider->computeRoute(
                $parsed->origin,
                $parsed->destination,
                $parsed->intermediates,
                $parsed->travelMode,
            );
        } catch (RoutingProviderException $exception) {
            $this->releaseReservedCreditAction->execute($user);

            throw $exception;
        }

        $conversion = Conversion::fromRoute($user, $rawUrl, $parsed, $route);
        $this->consumeReservedCreditAction->execute($user, $conversion);

        return $conversion;
    }
}
