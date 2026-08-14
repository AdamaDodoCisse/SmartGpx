<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Billing\Repository\CreditPurchaseRepository;
use App\Shared\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminPurchaseController extends AbstractController
{
    #[Route('/admin/purchases', name: 'app_admin_purchases_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, CreditPurchaseRepository $creditPurchaseRepository): Response
    {
        $paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));

        return $this->render('admin/purchases/index.html.twig', [
            'result' => $creditPurchaseRepository->findPageOrderedByCreatedAt($paginator),
        ]);
    }
}
