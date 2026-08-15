<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Repository\CreditPackRepository;
use App\Identity\Entity\User;
use App\Routing\Provider\RoutingProviderInterface;
use App\Usage\Repository\CreditAccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly CreditAccountRepository $creditAccountRepository,
        private readonly CreditPackRepository $creditPackRepository,
        private readonly RoutingProviderInterface $routingProvider,
    ) {
    }

    #[Route(['en' => '/', 'fr' => '/fr/'], name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $creditBalance = 0;

        if ($user instanceof User) {
            $account = $this->creditAccountRepository->findOneByUser($user);
            $creditBalance = $account?->getBalance() ?? 0;
        }

        return $this->render('home/index.html.twig', [
            'creditBalance' => $creditBalance,
            'packs' => $this->creditPackRepository->findActiveOrderedForDisplay(),
            // Statique (pas propre à l'utilisateur) — évite un aller-retour réseau
            // supplémentaire au montage de l'îlot pour connaître les capabilities du
            // fournisseur actif (voir RoutingCapabilitiesController pour l'équivalent API).
            'routingCapabilities' => $this->routingProvider->capabilities()->toArray(),
        ]);
    }
}
