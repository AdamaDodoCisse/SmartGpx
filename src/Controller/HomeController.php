<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Repository\CreditPackRepository;
use App\Identity\Entity\User;
use App\Shared\ConvertHero\ConvertHeroPropsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly CreditPackRepository $creditPackRepository,
        private readonly ConvertHeroPropsProvider $convertHeroPropsProvider,
    ) {
    }

    #[Route(['en' => '/', 'fr' => '/fr/'], name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $convertHeroProps = $this->convertHeroPropsProvider->forUser($user instanceof User ? $user : null);

        return $this->render('home/index.html.twig', [
            'creditBalance' => $convertHeroProps['creditBalance'],
            'packs' => $this->creditPackRepository->findActiveOrderedForDisplay(),
            'routingCapabilities' => $convertHeroProps['capabilities'],
        ]);
    }
}
