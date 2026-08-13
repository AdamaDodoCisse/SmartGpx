# Sécurité

## Mots de passe

Hachage via `security.yaml` (`password_hashers: { App\Identity\Entity\User: auto }`) —
l'algorithme (bcrypt/argon2/sodium) est choisi automatiquement selon la plateforme, sans code de
hachage personnalisé. Aucun mot de passe en clair n'est journalisé ou persisté ailleurs que dans
la colonne hachée.

## Vérification d'e-mail et réinitialisation de mot de passe

Délégué à deux bundles SymfonyCasts plutôt que ré-implémenté à la main :

- `symfonycasts/verify-email-bundle` : lien de vérification signé et expirant (HMAC sans état en
  base, juste `isVerified`/`verifiedAt` sur `User`).
- `symfonycasts/reset-password-bundle` : jeton haché + expiration + limitation par utilisateur,
  stockés dans `reset_password_request`.

Ces mécanismes sont réputés (timing-safe, expiration, usage unique) ; les ré-implémenter à la
main est une source classique de failles (prédictibilité de jeton, absence d'expiration).

Le flux de réinitialisation capture le jeton présent dans l'URL de l'e-mail et le déplace en
session avant d'afficher le formulaire (`ResetPasswordController::captureToken`), pour éviter
qu'il ne se retrouve dans l'historique du navigateur ou les journaux de referer.

## Limitation de débit (rate limiting)

- **Connexion** : `security.yaml` → `login_throttling: { max_attempts: 5, interval: '15 minutes' }`,
  géré nativement par `symfony/rate-limiter`.
- **Inscription** et **demande de réinitialisation** : limiteurs dédiés
  (`config/packages/rate_limiter.yaml`, 5/heure/IP), pour empêcher qu'un tiers utilise ces
  formulaires pour spammer des e-mails.
- Le stockage du rate limiter est adossé à Redis (`framework.cache.app`), y compris en
  environnement de test — voir `documentation/technique/base-de-donnees.md`.

## Anti-énumération de comptes

`RequestPasswordResetAction` affiche toujours le même message de confirmation, que l'e-mail
existe ou non, et génère un jeton factice (`generateFakeResetToken()`) dans le cas où le compte
n'existe pas, pour garder un temps de réponse comparable et ne pas révéler l'existence d'un
compte par une différence de timing.

## CSRF

Protection CSRF active sur les formulaires (`config/packages/csrf.yaml`,
`stateless_token_ids: [submit, authenticate, logout]`) et sur l'authentification/déconnexion.

## Identifiants publics

`User.publicId` (UUID v7) est l'identifiant exposé dans les URLs/liens (ex. lien de vérification
d'e-mail) — l'identifiant auto-incrémenté (`User.id`) n'est jamais exposé, pour éviter de
révéler le nombre d'inscrits ou de permettre une énumération triviale.

## Secrets

Aucun secret (mot de passe DB, futures clés API de routing/paiement) n'est committé : `.env` et
`.env.dev` ne contiennent que des valeurs de type placeholder ; les vraies valeurs vivent dans
`.env.local` / `.env.test.local` (gitignorés). Cette règle vaudra aussi pour les clés de routing
(Phase 2) et Stripe (Phase 4) : elles resteront exclusivement côté backend Symfony, jamais dans
le bundle React, l'extension Chrome, ou les journaux applicatifs.
