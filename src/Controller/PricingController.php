<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Repository\CreditPackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PricingController extends AbstractController
{
    #[Route(['en' => '/pricing', 'fr' => '/fr/pricing'], name: 'app_pricing')]
    public function index(Request $request, CreditPackRepository $creditPackRepository): Response
    {
        if ('cancelled' === $request->query->getString('checkout')) {
            $this->addFlash('info', 'billing.checkout.cancelled');
        }

        return $this->render('pricing/index.html.twig', [
            'packs' => $creditPackRepository->findActiveOrderedForDisplay(),
        ]);
    }
}
