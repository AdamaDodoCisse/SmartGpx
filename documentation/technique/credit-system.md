# Système de crédits

**Statut : implémenté (Phase 2).** Détail du raisonnement de concurrence et du modèle de
données dans [ADR-002](../decisions/ADR-002-credit-ledger.md) — ce document reste un point
d'entrée rapide côté code.

## Où c'est

```
src/Usage/
  Entity/{CreditAccount, CreditTransaction}.php
  Repository/{CreditAccountRepository, CreditTransactionRepository}.php
  Enum/CreditTransactionType.php
  Action/{GrantWelcomeCreditAction, ReserveCreditAction, ConsumeReservedCreditAction, ReleaseReservedCreditAction}.php
  EventListener/GrantWelcomeCreditOnRegistrationListener.php
  Exception/InsufficientCreditsException.php
```

## Flux

```
inscription
  → UserRegisteredEvent (émis par Identity\Action\RegisterUserAction)
  → GrantWelcomeCreditOnRegistrationListener → GrantWelcomeCreditAction
  → CreditAccount créé, solde = 1, ligne de ledger WELCOME

conversion Google Maps → GPX (Conversion\Action\ConvertGoogleMapsToGpxAction)
  → ReserveCreditAction           (échec → InsufficientCreditsException, HTTP 402)
  → appel au fournisseur de routing
      → échec → ReleaseReservedCreditAction (0 crédit débité)
      → succès → ConsumeReservedCreditAction (ligne de ledger CONVERSION, -1)
```

## Consulter un solde

```php
$account = $creditAccountRepository->findOneByUser($user); // peut être null (jamais converti)
$balance = $account?->getBalance() ?? 0;
```

Ne jamais lire/écrire `balance`/`reserved` autrement que via `CreditAccountRepository` (SQL
atomique) — voir ADR-002 pour le raisonnement de concurrence complet.

## Ce qui n'est pas encore fait

`PURCHASE` (achat via Stripe) et `REFUND` : Phase 4. `ADMIN_ADJUSTMENT` : Phase 8. L'enum
`CreditTransactionType` les définit déjà pour éviter une migration cassante, mais aucun code
actuel ne les produit.
