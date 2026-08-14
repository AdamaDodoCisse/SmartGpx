<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Billing\Action\CreateCreditPackAction;
use App\Billing\Action\UpdateCreditPackAction;
use App\Billing\Form\CreditPackFormType;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Request\CreditPackRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminCreditPackController extends AbstractController
{
    #[Route('/admin/credit-packs', name: 'app_admin_credit_packs_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(CreditPackRepository $creditPackRepository): Response
    {
        return $this->render('admin/credit_packs/index.html.twig', [
            'packs' => $creditPackRepository->findAllOrderedForAdmin(),
        ]);
    }

    #[Route('/admin/credit-packs/new', name: 'app_admin_credit_packs_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, CreateCreditPackAction $createCreditPackAction): Response
    {
        $dto = new CreditPackRequest();
        $form = $this->createForm(CreditPackFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $createCreditPackAction->execute($dto);

            return $this->redirectToRoute('app_admin_credit_packs_index');
        }

        return $this->render('admin/credit_packs/form.html.twig', [
            'creditPackForm' => $form,
            'isNew' => true,
        ]);
    }

    #[Route('/admin/credit-packs/{publicId}/edit', name: 'app_admin_credit_packs_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        string $publicId,
        Request $request,
        CreditPackRepository $creditPackRepository,
        UpdateCreditPackAction $updateCreditPackAction,
    ): Response {
        $pack = $creditPackRepository->findOneByPublicId($publicId);

        if (null === $pack) {
            throw $this->createNotFoundException();
        }

        $dto = new CreditPackRequest();
        $dto->credits = $pack->getCredits();
        $dto->priceCents = $pack->getPriceCents();
        $dto->currency = $pack->getCurrency();
        $dto->badge = $pack->getBadge();
        $dto->displayOrder = $pack->getDisplayOrder();
        $dto->active = $pack->isActive();

        $form = $this->createForm(CreditPackFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateCreditPackAction->execute($pack, $dto);

            return $this->redirectToRoute('app_admin_credit_packs_index');
        }

        return $this->render('admin/credit_packs/form.html.twig', [
            'creditPackForm' => $form,
            'isNew' => false,
        ]);
    }
}
