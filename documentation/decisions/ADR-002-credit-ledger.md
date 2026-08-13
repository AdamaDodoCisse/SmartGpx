# ADR-002 — Registre de crédits (ledger)

## Statut

À rédiger en Phase 4, lors de l'implémentation du système de crédits/paiement. Le principe d'un
registre immuable (`CreditTransaction`) plutôt qu'un simple solde muable est déjà acté par le
brief produit (§40-41) ; cette ADR documentera le modèle de concurrence retenu (réservation
atomique, verrouillage transactionnel) une fois implémenté.
