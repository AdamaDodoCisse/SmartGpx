# Authentification

**Statut : implémenté (Phase 1).**

## Inscription

`/register` (`/fr/register` en français) — e-mail + mot de passe uniquement (pas de champ nom en
Phase 1). Règles :

- e-mail déjà utilisé → erreur affichée sur le champ, aucun second compte créé ;
- limitation à 5 inscriptions par heure et par IP (protection anti-spam) ;
- le compte créé est **non vérifié** par défaut ; un e-mail de confirmation est envoyé
  immédiatement (lien signé, valable 1h, visible dans le panneau Mailer du profiler en
  développement puisqu'aucun SMTP réel n'est configuré).

## Vérification d'e-mail

`/verify/email?id=...&expires=...&signature=...` — lien reçu par e-mail. Un lien invalide ou
expiré redirige vers `/register` avec un message d'erreur ; un lien valide marque le compte
vérifié et redirige vers `/login`.

## Connexion / déconnexion

`/login` (`/fr/login`) — formulaire e-mail + mot de passe. Règles :

- 5 tentatives échouées maximum sur 15 minutes (au-delà : message « Too many failed login
  attempts... », quel que soit le mot de passe soumis) ;
- déconnexion via `/logout`, gérée entièrement par la configuration du firewall (aucun code
  applicatif).

## Mot de passe oublié / réinitialisation

`/forgot-password` (`/fr/forgot-password`) — formulaire e-mail. Le message affiché est
**toujours identique**, que le compte existe ou non, pour ne pas révéler l'existence d'un
compte. Limité à 5 demandes par heure et par IP.

Si le compte existe, un e-mail est envoyé avec un lien vers `/reset-password/{token}` (validité
1h, 1 demande active par heure et par utilisateur). Le jeton est immédiatement déplacé en
session et retiré de l'URL avant l'affichage du formulaire de nouveau mot de passe, pour éviter
qu'il ne se retrouve dans l'historique du navigateur.

## Google Sign-In

Implémenté via `App\Identity\Security\GoogleAuthenticator`, un `Authenticator` Symfony
additionnel enregistré sur le firewall `main` existant (aucune nouvelle interface de domaine —
voir `documentation/technique/architecture.md`, section « Interfaces aux frontières externes » :
Google Sign-In n'a qu'un seul fournisseur réel, contrairement à Routing/Billing). Le bouton
« Continue with Google » apparaît sur `/login` et `/register` : un seul flux couvre les deux cas,
il n'existe pas de « inscription Google » distincte de la « connexion Google ».

Comportement exact (`App\Identity\Action\AuthenticateWithGoogleAction`) :

1. Un `googleId` déjà connu → reconnexion, aucune donnée modifiée.
2. Sinon, une adresse e-mail déjà utilisée par un compte local (mot de passe) → **liaison
   automatique** si Google rapporte cette adresse comme vérifiée (`email_verified: true`) : le
   `googleId` est enregistré sur le compte existant, le mot de passe reste intact (les deux modes
   de connexion continuent de fonctionner ensuite). C'est le comportement standard (GitHub,
   Auth0, Firebase Auth font pareil) — sûr ici car c'est Google lui-même qui garantit la
   possession de l'adresse. Si Google rapporte l'adresse comme **non vérifiée** (cas rare, comptes
   Google Workspace), la liaison est refusée avec un message clair plutôt que silencieusement
   acceptée.
3. Sinon, création d'un nouveau compte : `AuthProvider::GOOGLE`, pas de mot de passe,
   **vérifié immédiatement** (`isVerified = true`) — Google a déjà prouvé la possession de
   l'adresse, inutile de renvoyer un e-mail de confirmation SmartGPX. `UserRegisteredEvent` est
   émis exactement comme pour une inscription classique, donc le crédit de bienvenue est accordé
   de la même façon (voir plus bas).

Voir `documentation/technique/google-sign-in.md` pour le détail technique (routes, configuration
`knpu_oauth2_client.yaml`, mise en place des identifiants Google Cloud Console).

## Utilisation par d'autres domaines

Depuis la Phase 2, `RegisterUserAction` émet `Identity\Event\UserRegisteredEvent` après la
création du compte, pour que d'autres domaines puissent réagir à une inscription sans
qu'`Identity` ait besoin de les connaître — voir
[ADR-002](../decisions/ADR-002-credit-ledger.md) (crédit de bienvenue). L'API de conversion
Google Maps → GPX (`POST /api/conversions/google-maps`) exige une session authentifiée — voir
`documentation/technique/api.md`.

## Ce qui n'est pas encore fait

- Suppression de compte / historique (prévue par le brief produit, §71) — pas encore de route
  dédiée.
- Interface de gestion du compte (changement d'e-mail, etc.) — hors périmètre Phase 1/2.
