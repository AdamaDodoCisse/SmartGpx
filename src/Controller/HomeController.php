<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Repository\CreditPackRepository;
use App\Identity\Entity\User;
use App\Usage\Repository\CreditAccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly CreditAccountRepository $creditAccountRepository,
        private readonly CreditPackRepository $creditPackRepository,
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
        ]);
    }
}
