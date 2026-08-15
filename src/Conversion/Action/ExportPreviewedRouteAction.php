<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Entity\Conversion;
use App\Conversion\Exception\InvalidRouteSelectionException;
use App\Conversion\Exception\RoutePreviewNotFoundException;
use App\Conversion\Service\RoutePreviewStore;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Usage\Action\ConsumeReservedCreditAction;
use App\Usage\Action\ReleaseReservedCreditAction;
use App\Usage\Action\ReserveCreditAction;
use App\Usage\Exception\InsufficientCreditsException;

/**
 * Deuxième étape du flux "choisir son itinéraire" : facture réellement 1 crédit et persiste le
 * Conversion pour l'itinéraire choisi parmi ceux calculés par PreviewGoogleMapsRoutesAction — le
 * calcul lui-même n'est jamais refait, seul le previewId est relu depuis le cache. Un aperçu
 * exporté avec succès est retiré du cache (usage unique) ; un échec le laisse disponible jusqu'à
 * son expiration naturelle, pour permettre une nouvelle tentative sans recalcul.
 */
final class ExportPreviewedRouteAction
{
    public function __construct(
        private readonly RoutePreviewStore $previewStore,
        private readonly ReserveCreditAction $reserveCreditAction,
        private readonly ConsumeReservedCreditAction $consumeReservedCreditAction,
        private readonly ReleaseReservedCreditAction $releaseReservedCreditAction,
    ) {
    }

    /**
     * @throws EmailNotVerifiedException
     * @throws RoutePreviewNotFoundException
     * @throws InvalidRouteSelectionException
     * @throws InsufficientCreditsException
     */
    public function execute(User $user, string $previewId, int $selectedIndex): Conversion
    {
        if (!$user->isVerified()) {
            throw new EmailNotVerifiedException($user);
        }

        $preview = $this->previewStore->retrieve($previewId, $user);

        if ($selectedIndex < 0 || $selectedIndex >= \count($preview->computation->routes)) {
            throw new InvalidRouteSelectionException(sprintf('No candidate route at index %d.', $selectedIndex));
        }

        $this->reserveCreditAction->execute($user);

        $selectedRoute = $preview->computation->routes[$selectedIndex];
        $conversion = Conversion::fromRoute(
            $user,
            $preview->sourceUrl,
            $preview->parsed,
            $selectedRoute,
            $preview->options,
            $preview->computation->costTier,
            $preview->waypointTypes,
        );

        try {
            $this->consumeReservedCreditAction->execute($user, $conversion);
        } catch (\Throwable $exception) {
            $this->releaseReservedCreditAction->execute($user);

            throw $exception;
        }

        $this->previewStore->forget($previewId);

        return $conversion;
    }
}
