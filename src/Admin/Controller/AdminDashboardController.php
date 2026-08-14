<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Action\ComputeAdminMetricsAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(ComputeAdminMetricsAction $computeAdminMetricsAction): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'metrics' => $computeAdminMetricsAction->execute(),
        ]);
    }
}
