# Admin

**Statut : implémenté (Phase 8).**

Interface simple pour opérer le produit — cinq écrans, aucune interactivité côté client (Twig
pur, formulaires en POST classique). Voir
[ADR-007](../decisions/ADR-007-admin-access-control.md) pour le modèle de contrôle d'accès.

## Accès

`ROLE_ADMIN` s'accorde uniquement via `bin/console app:user:promote-admin <email>` — jamais
depuis l'interface elle-même. Toute route `/admin/*` redirige un visiteur anonyme vers la
connexion et renvoie 403 à un compte simple `ROLE_USER`.

## Écrans

| Écran | Route | Contenu |
|---|---|---|
| Tableau de bord | `/admin` | Utilisateurs totaux, crédits émis/consommés, conversions réussies/échouées, achats complétés, revenu, packs actifs |
| Utilisateurs | `/admin/users` | Liste paginée (email, rôles, vérification, date d'inscription) |
| Détail utilisateur | `/admin/users/{publicId}` | Ledger de crédits paginé + formulaire d'ajustement manuel |
| Achats | `/admin/purchases` | Liste paginée des achats de crédits (utilisateur, crédits, montant, statut, date) |
| Packs de crédits | `/admin/credit-packs` | Catalogue complet (actifs et inactifs) + création/édition |
| Conversions échouées | `/admin/conversions/failed` | Liste paginée (utilisateur, URL, raison, date) |

## Ajustement manuel de crédits

Depuis la fiche d'un utilisateur, un admin peut lui accorder des crédits (montant strictement
positif — aucun débit manuel n'existe). L'ajustement apparaît dans le ledger de l'utilisateur
avec le type `admin_adjustment`.

## Catalogue de packs de crédits

Création et édition complètes (crédits, prix, devise, badge, ordre d'affichage, actif/inactif) —
un pack désactivé disparaît de `/pricing` mais reste visible et modifiable dans la liste admin.
Pas de suppression : un pack déjà acheté conserve sa trace via les champs figés de
`CreditPurchase` ; désactiver suffit.

## Conversions échouées

Chaque tentative de conversion Google Maps → GPX qui échoue (URL non reconnue, crédits
insuffisants, itinéraire introuvable, fournisseur de routing indisponible) est désormais
enregistrée — que la tentative vienne du site web ou de l'extension Chrome. Voir
`documentation/technique/admin.md` pour le détail technique.

## Non-objectifs (volontairement hors scope de cette phase)

- Pas de recherche ni de filtre sur les listes — un simple défilement par page suffit à l'échelle
  actuelle.
- Pas de débit manuel de crédits, seulement un crédit.
- Pas de suppression de pack, seulement une désactivation.
- Pas de traçabilité de « quel admin a fait quel ajustement » — voir la limite connue documentée
  dans l'ADR-007.
