# Extension Chrome

**Statut : implémenté (Phase 3).** Vérification manuelle de bout en bout (extension chargée en
mode développeur, connexion, export réel, révocation) à réaliser avant toute publication — voir
`chrome-extension/RELEASE_CHECKLIST.md`.

## Vue d'ensemble

```
chrome-extension/                    # projet npm séparé de assets/app/
  manifest.config.ts                 # manifest MV3 piloté par TypeScript (@crxjs/vite-plugin)
  src/
    popup/                           # UI React affichée au clic sur l'icône
      Popup.tsx, components/{ConnectPrompt,RouteSummary,CreditBadge,ExportButton,ErrorState}.tsx
    background/service-worker.ts     # unique point d'accès au jeton et à fetch()
    lib/{env,auth,api,mapsUrl,messages,i18n}.ts
  icons/                             # PNG de substitution (16/32/48/128) — vrai visuel hors périmètre
```

Projet npm distinct de `assets/app/` : artefact déployable différent (un `.zip`, pas servi par
`public/build/`), CSP fixe non assouplissable des pages d'extension (`script-src 'self';
object-src 'self'` — aucun script distant, satisfait d'office l'exigence du brief), points
d'entrée pilotés par le manifest (`background.service_worker`, `action.default_popup`) que la
configuration de `assets/app` n'a aucune raison de connaître.

## Authentification

Voir [ADR-005](../decisions/ADR-005-extension-authentication.md) pour le raisonnement complet
(jeton opaque, firewall dédié, CSRF non pertinent, prise de contrôle par `externally_connectable`).
En bref : `/account/extensions/connect` génère un jeton et le transmet au service worker via
`chrome.runtime.sendMessage`, qui le stocke dans `chrome.storage.local` aux côtés de l'origine
API. Le popup ne lit jamais le jeton directement — seul le service worker y accède.

## Permissions

`activeTab`, `storage`, `downloads` — **aucun `host_permissions` pour `google.*`, aucun script
de contenu**. Le popup lit l'URL de l'onglet actif à la demande via `chrome.tabs.query`,
autorisé uniquement au clic sur l'icône de la barre d'outils par `activeTab` — inutile de
demander un accès permanent à tous les onglets Google Maps. `host_permissions` est limité à
l'origine de l'API SmartGPX elle-même, nécessaire au `fetch()` du service worker. Justification
détaillée par permission : `chrome-extension/PRIVACY_DISCLOSURE.md`.

## `lib/mapsUrl.ts` — détection sans script de contenu

`isGoogleMapsRouteUrl()` : vérifie qu'une URL correspond plausiblement à un itinéraire Google
Maps exportable (formats `/maps/dir/...`, `?api=1&origin=...&destination=...`, ou un lien court
`maps.app.goo.gl`/`goo.gl` — accepté tel quel, la résolution du lien court se fait côté backend
comme pour le flux web). `parseRoutePreview()` : extraction *best-effort*, côté client, de
l'origine/destination pour l'aperçu affiché dans le popup avant dépense d'un crédit — ne couvre
que le format « chemin » ; le format `?api=1&...` et les liens courts affichent un aperçu
générique. L'analyse faisant foi reste `GoogleMapsUrlParser` côté serveur (Phase 2), appelée au
clic sur Exporter — ces fonctions ne servent qu'à l'UI, jamais à la validation.

## Service worker — proxy fetch et téléchargement

Le popup est un document éphémère détruit dès qu'il perd le focus ; un `fetch()` démarré
*depuis le popup* serait interrompu si l'utilisateur cliquait ailleurs avant la réponse. Le
popup communique donc avec le service worker par `chrome.runtime.sendMessage`/`onMessage`
(contrat typé dans `lib/messages.ts` : `GET_ACCOUNT`, `CONVERT`, `DOWNLOAD`). Les service workers
MV3 ne sont pas persistants — rien en mémoire ne survit entre deux événements — le jeton est donc
relu depuis `chrome.storage.local` à chaque appel plutôt que mis en cache dans le module.

Téléchargement (`DOWNLOAD`) : le service worker récupère le GPX avec l'en-tête `Authorization`,
convertit le blob en data URL base64 via `FileReader.readAsDataURL` — **pas**
`URL.createObjectURL`, dont l'URL générée n'est valide que dans le document qui l'a créée, ce qui
est peu fiable si le service worker est déchargé entre le `fetch` et l'appel à
`chrome.downloads.download()` — puis appelle `chrome.downloads.download()`.

Toute réponse 401 (jeton révoqué) déclenche l'effacement de `chrome.storage.local` côté service
worker ; le popup retombe alors sur l'état « non connecté » à sa prochaine ouverture ou au
prochain appel.

## Popup — états

`loading` → `not-connected` (pas de jeton stocké → bouton ouvrant `/account/extensions/connect`)
→ selon l'onglet actif : message « ouvrez un itinéraire » ou aperçu + bouton Exporter → `converting`
→ confirmation inline en cas de succès (avec rafraîchissement du solde de crédits), ou message
d'erreur traduit renvoyé tel quel par l'API en cas d'échec.

Copie des crédits (`lib/i18n.ts`, dictionnaire autonome — pas d'import croisé depuis
`assets/app/src/i18n`, builds séparés) : « 1 conversion gratuite disponible » tant qu'aucune
conversion n'a jamais réussi et qu'il reste du solde ; « N crédits restants » sinon ; à 0, un
lien vers `/pricing`.

## Tests

`npm run typecheck` (même niveau de rigueur que `assets/app`, `strict: true`). `vitest` :
`lib/mapsUrl.test.ts` (détection/aperçu sur formats réels et rejetés), `lib/auth.test.ts`
(helpers de stockage contre un mock de `chrome.storage.local`). Pas d'automatisation navigateur
de bout en bout (Puppeteer/Playwright) — hors périmètre ; la vérification manuelle est couverte
par `chrome-extension/RELEASE_CHECKLIST.md`.
