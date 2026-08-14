<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Shared\Pagination\Paginator;
use App\Usage\Action\GrantAdminCreditAdjustmentAction;
use App\Usage\Form\CreditAdjustmentFormType;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use App\Usage\Request\CreditAdjustmentRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminUserController extends AbstractController
{
    #[Route('/admin/users', name: 'app_admin_users_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));

        return $this->render('admin/users/index.html.twig', [
            'result' => $userRepository->findPageOrderedByCreatedAt($paginator),
        ]);
    }

    #[Route('/admin/users/{publicId}', name: 'app_admin_users_show')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(
        string $publicId,
        Request $request,
        UserRepository $userRepository,
        CreditAccountRepository $creditAccountRepository,
        CreditTransactionRepository $creditTransactionRepository,
    ): Response {
        $user = $this->findUserOrFail($publicId, $userRepository);
        $form = $this->createForm(CreditAdjustmentFormType::class, new CreditAdjustmentRequest());

        return $this->renderShow($user, $request, $creditAccountRepository, $creditTransactionRepository, $form);
    }

    #[Route('/admin/users/{publicId}/credit-adjustment', name: 'app_admin_users_credit_adjustment', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function creditAdjustment(
        string $publicId,
        Request $request,
        UserRepository $userRepository,
        CreditAccountRepository $creditAccountRepository,
        CreditTransactionRepository $creditTransactionRepository,
        GrantAdminCreditAdjustmentAction $grantAdminCreditAdjustmentAction,
    ): Response {
        $user = $this->findUserOrFail($publicId, $userRepository);

        $dto = new CreditAdjustmentRequest();
        $form = $this->createForm(CreditAdjustmentFormType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderShow($user, $request, $creditAccountRepository, $creditTransactionRepository, $form);
        }

        $grantAdminCreditAdjustmentAction->execute($user, $dto->amount);
        $this->addFlash('success', sprintf('Granted %d credits to %s.', $dto->amount, $user->getEmail()));

        return $this->redirectToRoute('app_admin_users_show', ['publicId' => $publicId]);
    }

    private function findUserOrFail(string $publicId, UserRepository $userRepository): User
    {
        $user = $userRepository->findOneByPublicId($publicId);

        if (null === $user) {
            throw $this->createNotFoundException();
        }

        return $user;
    }

    /**
     * @param FormInterface<CreditAdjustmentRequest> $creditAdjustmentForm
     */
    private function renderShow(
        User $user,
        Request $request,
        CreditAccountRepository $creditAccountRepository,
        CreditTransactionRepository $creditTransactionRepository,
        FormInterface $creditAdjustmentForm,
    ): Response {
        $creditAccount = $creditAccountRepository->findOneByUser($user);
        $ledger = null;

        if (null !== $creditAccount) {
            $paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));
            $ledger = $creditTransactionRepository->findPageForAccountOrderedByCreatedAt($creditAccount, $paginator);
        }

        return $this->render('admin/users/show.html.twig', [
            'targetUser' => $user,
            'creditAccount' => $creditAccount,
            'ledger' => $ledger,
            'creditAdjustmentForm' => $creditAdjustmentForm->createView(),
        ]);
    }
}
