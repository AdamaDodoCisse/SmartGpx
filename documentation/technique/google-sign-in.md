# Google Sign-In

**Statut : implémenté, vérifié par tests automatisés (logique métier + forme de la redirection).
La vérification manuelle de bout en bout contre un vrai compte Google (identifiants OAuth réels,
clic réel sur l'écran de consentement Google) reste à faire** — voir « Tester en local »
ci-dessous ; même scoping honnête que Stripe/Google Routes : compléter réellement un login Google
en automatisé n'est pas raisonnable (Google bloque activement les tentatives de connexion
automatisées, et il n'est pas question de saisir un vrai mot de passe Google dans un test).

## Où c'est

```
src/Identity/
  ValueObject/GoogleIdentity.php                 # DTO plat : googleId, email, emailVerified
  Action/AuthenticateWithGoogleAction.php         # toute la logique métier, testable sans réseau
  Repository/UserRepository.php                  # + findOneByGoogleId()
  Exception/GoogleEmailNotVerifiedException.php
  Security/GoogleAuthenticator.php                # extends OAuth2Authenticator (KnpU)
  Controller/GoogleConnectController.php          # /connect/google, /connect/google/check
```

Config : `config/packages/knpu_oauth2_client.yaml` (client `google`, type `league/oauth2-google`),
`config/packages/security.yaml` (`GoogleAuthenticator` ajouté aux `custom_authenticators` du
firewall `main`, `/connect/google` en accès public).

## Pourquoi pas de nouvelle interface de domaine

Contrairement à `RoutingProviderInterface`/`BillingProviderInterface`, il n'existe qu'un seul
fournisseur Google réel — `documentation/technique/architecture.md` déconseille explicitement une
interface spéculative dans ce cas. `GoogleAuthenticator` est un simple `Authenticator` Symfony,
pas une abstraction de domaine.

La frontière testable n'est donc pas l'échange OAuth lui-même (qui nécessite un vrai réseau vers
Google), mais `AuthenticateWithGoogleAction` : elle ne prend qu'un DTO plat (`GoogleIdentity`) en
entrée, zéro dépendance vers les types du SDK OAuth, donc testable en `KernelTestCase` sans aucun
mock réseau — voir `tests/Identity/Action/AuthenticateWithGoogleActionTest.php`.

## Comportement (`AuthenticateWithGoogleAction`)

1. `googleId` déjà connu → renvoie l'utilisateur existant, rien n'est modifié.
2. Sinon, e-mail déjà utilisé par un compte local : si Google rapporte `email_verified: true`,
   liaison automatique (`setGoogleId()`, mot de passe intact) ; sinon
   `GoogleEmailNotVerifiedException` — jamais de liaison sur une adresse non confirmée par Google.
3. Sinon, nouveau compte : `AuthProvider::GOOGLE`, pas de mot de passe, `isVerified = true`
   immédiatement (Google a déjà prouvé la possession de l'adresse), `UserRegisteredEvent` émis —
   le crédit de bienvenue est donc accordé exactement comme pour une inscription classique, sans
   aucun changement dans `src/Usage/`.

Voir `documentation/fonctionnel/authentification.md` pour la version orientée produit de ce même
comportement.

## Flux

```
/login ou /register → bouton « Continue with Google »
  → GET /connect/google → redirection vers l'écran de consentement Google
    (scopes: openid, email, profile — "openid" ajouté automatiquement par use_oidc_mode)
  → GET /connect/google/check?code=...&state=...
      → GoogleAuthenticator::authenticate() : échange le code, récupère le GoogleUser,
        construit un GoogleIdentity, appelle AuthenticateWithGoogleAction
      → succès : connecté, redirection vers la page d'accueil
      → échec (adresse non vérifiée) : redirection vers /login avec un message d'erreur
```

`GoogleConnectController::connectCheck()` n'est jamais réellement exécutée : `GoogleAuthenticator`
intercepte la requête avant que le contrôleur ne soit atteint (même principe que
`SecurityController::logout()`).

## Configurer les identifiants (Google Cloud Console)

1. Dans le projet Google Cloud existant de SmartGPX (celui qui fournit déjà
   `GOOGLE_ROUTES_API_KEY`) : **APIs & Services → Credentials → Create Credentials → OAuth
   client ID**, type **Web application**.
2. **Authorized redirect URI** : `https://127.0.0.1:8000/connect/google/check` en local (avec
   `symfony serve`, HTTPS), le domaine de production équivalent une fois déployé.
3. Si l'écran de consentement OAuth n'a jamais été configuré pour ce projet, il faut le faire une
   fois (nom de l'app, e-mail de support) — Google l'exige avant de délivrer des identifiants.
4. Copier le **Client ID** et le **Client secret** dans `.env.local` (jamais dans `.env`, jamais
   committé) :
   ```
   GOOGLE_OAUTH_CLIENT_ID=...
   GOOGLE_OAUTH_CLIENT_SECRET=...
   ```

## Tester en local

Avec de vrais identifiants dans `.env.local` (voir ci-dessus) : `symfony serve`, ouvrir
`https://127.0.0.1:8000/login`, cliquer « Continue with Google », se connecter avec un vrai compte
Google. Vérifier : redirection vers l'accueil, session ouverte, `User` créé/lié en base avec le
bon `googleId`, et — pour un nouveau compte — 1 crédit de bienvenue sur `/account/credits`.

Sans identifiants réels (`changeme` dans `.env`), `GET /connect/google` construit quand même une
redirection valide vers `accounts.google.com` (c'est ce que vérifie le test fonctionnel) — seul
l'échange du `code` contre un token échoue, puisque Google rejette un `client_id` inconnu.
