<?php

declare(strict_types=1);

namespace App\Usage\Controller;

use App\Identity\Entity\User;
use App\Shared\Pagination\Paginator;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Historique des crédits de l'utilisateur connecté — lecture seule, réutilise le même
 * CreditTransactionRepository::findPageForAccountOrderedByCreatedAt et le même Paginator que le
 * ledger admin (src/Admin/Controller/AdminUserController.php), simplement scopé à l'utilisateur
 * courant plutôt qu'à un publicId choisi par un opérateur.
 */
final class CreditLedgerController extends AbstractController
{
    #[Route(['en' => '/account/credits', 'fr' => '/fr/compte/credits'], name: 'app_account_credits')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        CreditAccountRepository $creditAccountRepository,
        CreditTransactionRepository $creditTransactionRepository,
    ): Response {
        $user = $this->currentUser();
        $creditAccount = $creditAccountRepository->findOneByUser($user);
        $ledger = null;

        if (null !== $creditAccount) {
            $paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));
            $ledger = $creditTransactionRepository->findPageForAccountOrderedByCreatedAt($creditAccount, $paginator);
        }

        return $this->render('usage/credits/index.html.twig', [
            'creditAccount' => $creditAccount,
            'ledger' => $ledger,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
