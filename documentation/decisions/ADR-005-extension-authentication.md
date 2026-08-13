# ADR-005 — Authentification de l'extension Chrome

## Statut

Acceptée et vérifiée par tests fonctionnels (Phase 3).

## Contexte

L'extension Chrome (`chrome-extension/`) doit appeler l'API SmartGPX depuis un service worker
MV3, sans jamais partager le cookie de session du navigateur (l'extension et l'application web
ne s'exécutent pas dans le même contexte d'origine/document) et sans demander de mot de passe.
Le brief exige explicitement que l'accès soit **révocable depuis le compte**, à tout moment,
sans que cela nécessite de changer le mot de passe du compte.

C'est la première frontière d'authentification non basée sur la session de l'application — un
choix structurant qui touche `security.yaml`, un nouveau domaine `src/Extension/`, et la façon
dont `ConvertGoogleMapsToGpxAction` (Phase 2) est réutilisée sans modification.

## Décision

### Jeton opaque en base, pas un JWT

Aucune bibliothèque JWT n'est installée dans le projet. Un JWT signé apporterait de la
vérification sans aller-retour base de données, mais au prix d'une révocation immédiate
non triviale (liste de révocation, ou expiration courte + refresh — complexité inutile face à
l'exigence réelle). Un jeton opaque avec une ligne `ExtensionAuthorization` en base résout la
révocation par construction : révoquer, c'est poser `revokedAt`, vérifié à chaque requête via
`ExtensionAuthorizationRepository::findActiveByTokenHash()`.

`GenerateExtensionAuthorizationAction` génère `'sgpx_ext_' . bin2hex(random_bytes(32))` (256 bits
d'entropie), retourné en clair une seule fois. Seul `hash('sha256', $plaintext)` est persisté —
**pas bcrypt/argon2** : ces fonctions existent pour ralentir la force brute d'un secret à faible
entropie choisi par un humain (mot de passe) ; un jeton aléatoire à 256 bits n'en a pas besoin,
et l'extension appelle l'API à quasiment chaque ouverture du popup — un hash volontairement lent
serait une taxe de latence sans bénéfice de sécurité réel. Même raisonnement que les jetons API
opaques de GitHub, Stripe ou Laravel Sanctum.

`ExtensionAuthorization` n'est **jamais supprimée**, y compris après révocation — même
philosophie de piste d'audit que le registre de crédits (voir `[[ADR-002-credit-ledger]]`) :
`/account/extensions` continue d'afficher une autorisation révoquée avec un badge « Révoqué le
… », pas une ligne disparue.

### Firewall dédié plutôt qu'authenticator partagé

`config/packages/security.yaml` déclare un firewall `api_extension` séparé, `stateless: true`,
placé avant `main` :

```yaml
firewalls:
    api_extension:
        pattern: ^/api/extension/
        stateless: true
        provider: app_user_provider
        custom_authenticators:
            - App\Extension\Security\ExtensionTokenAuthenticator
    main:
        # inchangé — authentification par session, CSRF actif
```

Alternative envisagée et rejetée : ajouter `ExtensionTokenAuthenticator` comme second
`custom_authenticators` du firewall `main` existant. Rejetée parce que
`ConvertGoogleMapsController::create()` (Phase 2) appelle déjà `isCsrfTokenValid()`, qui dépend
du gestionnaire de jetons CSRF **basé sur la session**. Une route authentifiée par jeton mais
routée à travers le même contrôleur, sur le même firewall que le flux session, exigerait une
règle permanente et facile à oublier (« ignorer le CSRF si un en-tête `Authorization` est
présent ») sur chaque route actuelle et future de `main`. Un firewall `stateless: true` dédié
fait de cette propriété — sans session, sans CSRF — un fait structurel garanti par Symfony
(`ContextListener` est désactivé pour ce firewall, aucune session n'est jamais créée ni lue),
au lieu d'une discipline à maintenir manuellement.

`src/Extension/Controller/ExtensionConversionController.php` est un contrôleur dédié, distinct de
`ConvertGoogleMapsController`, mais appelle la **même** `ConvertGoogleMapsToGpxAction` — Phase 2
avait déjà rendu cette action agnostique du canal de transport. Le formatage JSON de réponse est
partagé via `App\Conversion\Http\ConversionJsonPresenter`, extrait à cette occasion pour éviter
la duplication entre les deux contrôleurs.

### Le CSRF ne s'applique pas à `/api/extension/*`

Pas « désactivé par exception » — réellement **non pertinent**. Le CSRF protège contre une
requête forgée par un site tiers qui exploite une créance *ambiante* que le navigateur attache
automatiquement (un cookie de session). Un jeton `Bearer` que le code de l'extension attache
explicitement à chaque appel n'est pas ambiant : une page web tierce ne peut pas le connaître
ni le faire attacher à son insu. Cette distinction est la raison structurelle du choix de
firewall ci-dessus, pas une simplification de confort.

### Authentification de la requête

`ExtensionTokenAuthenticator::supports()` détecte un en-tête `Authorization: Bearer …`.
`authenticate()` hashe le jeton, cherche une autorisation active
(`findActiveByTokenHash`, `WHERE token_hash = ? AND revoked_at IS NULL`), lève
`CustomUserMessageAuthenticationException` si absente ou révoquée, met à jour `lastUsedAt` via
une requête DBAL brute à une seule colonne (`touchLastUsedAt`, même pattern que
`CreditAccountRepository::reserveOne()` — pas de flush d'entité complet sur le chemin chaud), et
retourne un `SelfValidatingPassport` avec un `UserBadge` fermé sur l'utilisateur déjà chargé
(pas de second aller-retour `loadUserByIdentifier`).

### Prise de contrôle (handoff) sans OAuth ni copier-coller

`/account/extensions/connect` (session web, CSRF classique) génère un jeton frais côté serveur et
le transmet à l'extension via `externally_connectable` + `chrome.runtime.sendMessage` — pas
`chrome.identity.launchWebAuthFlow` (réservé à un vrai fournisseur OAuth tiers, absent ici :
SmartGPX est son propre fournisseur d'identité). Le service worker en arrière-plan
(`onMessageExternal`) vérifie `sender.origin` contre la même liste d'origines déclarée dans
`manifest.config.ts` avant de stocker `{ token, apiOrigin }` dans `chrome.storage.local` —
`storage.local` plutôt que `.session` : le brief attend un flux aussi proche que possible d'un
seul clic, y compris après redémarrage du navigateur ; la protection contre un jeton qui fuit est
la révocation depuis le compte, pas le chiffrement au repos.

## Vérification effectuée

Test fonctionnel `ExtensionConversionControllerTest::testARevokedTokenStopsWorkingImmediately` :
un jeton valide réussit un appel, l'autorisation est révoquée, **le même jeton** échoue
immédiatement (401) sur l'appel suivant — aucune mise en cache, aucune latence de propagation.
Couverture complémentaire : en-tête absent/malformé/inconnu → 401 ; isolation par propriétaire
sur `/account/extensions/{publicId}/revoke` → 404 pour un utilisateur non propriétaire (voir
`AccountExtensionControllerTest`).
