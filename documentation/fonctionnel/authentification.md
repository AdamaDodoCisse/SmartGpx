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

**Non implémenté en Phase 1.** Le schéma de `User` (mot de passe nullable, colonne
`authProvider`, colonne `googleId`) est prêt pour l'ajouter plus tard sans migration
destructive, via un `Authenticator` Symfony additionnel enregistré sur le même firewall — voir
`documentation/technique/architecture.md` (section « Interfaces aux frontières externes »).

## Ce qui n'est pas encore fait

- Suppression de compte / historique (prévue par le brief produit, §71) — pas encore de route
  dédiée.
- Interface de gestion du compte (changement d'e-mail, etc.) — hors périmètre Phase 1.
