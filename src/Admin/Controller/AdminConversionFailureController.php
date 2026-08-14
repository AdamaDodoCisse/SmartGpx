<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Conversion\Repository\ConversionFailureRepository;
use App\Shared\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminConversionFailureController extends AbstractController
{
    #[Route('/admin/conversions/failed', name: 'app_admin_conversions_failed')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, ConversionFailureRepository $conversionFailureRepository): Response
    {
        $paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));

        return $this->render('admin/conversion_failures/index.html.twig', [
            'result' => $conversionFailureRepository->findPageOrderedByCreatedAt($paginator),
        ]);
    }
}
