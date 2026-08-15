# Déploiement

**Statut : pas encore déployé.** Cible retenue : **Infomaniak**. Ce document part de l'hypothèse
d'un serveur avec accès SSH complet (VPS "Cloud Server" ou Public Cloud chez Infomaniak) — pas
l'hébergement web mutualisé classique, qui ne donne généralement ni accès root pour installer
Redis, ni la possibilité de lancer un build Node, ni le contrôle du pool PHP-FPM dont l'application
a besoin. Si l'offre retenue chez Infomaniak est finalement différente, la section
["Hypothèses à confirmer"](#hypothèses-à-confirmer) liste précisément ce qui doit être revu.

Rien de ce qui suit n'est encore automatisé : aucun `Dockerfile`, aucun outil de déploiement
(Deployer, Capistrano...), aucun step de déploiement dans la CI n'existe dans ce dépôt à ce jour —
seulement un pipeline de QA (`.github/workflows/ci.yml`, build + tests, jamais de déploiement). Ce
document décrit la procédure manuelle de référence ; l'automatiser est un travail à part.

## Ce que le serveur doit fournir

| Composant | Version / détail |
| --- | --- |
| PHP | **8.4+** (contrainte `composer.json`), extensions `ctype`, `iconv`, `mbstring`, `pdo_mysql`, `redis` — `opcache` fortement recommandé en production (non requis par le code, mais standard pour tout déploiement PHP-FPM) |
| MySQL | **8.0** (`DATABASE_URL` attend `mysql://...?serverVersion=8.0`) |
| Redis | Voir [la section dédiée](#redis--ce-qui-en-dépend-réellement) — sert le pool de cache principal, le rate limiter et le login throttling, et le cache de résultats Doctrine. **Ne sert pas** les sessions PHP (handler natif par défaut, non Redis) |
| Node.js | **22** — uniquement pour construire les assets (`npm run build`), pas nécessaire à l'exécution une fois `public/build/` généré |
| Composer | 2.x |
| Serveur web | Nginx ou Apache + PHP-FPM — aucune config n'existe encore dans le dépôt (ni `nginx.conf`, ni `.htaccess`) ; exemple Nginx plus bas |

Le front controller est `public/index.php`, basé sur `symfony/runtime` (pas de bootstrap
personnalisé à écrire) — document root = `public/`.

## Variables d'environnement en production

Toutes ces variables ont un placeholder committé dans `.env` (jamais de vraie valeur) — les
valeurs réelles vont dans `.env.local` sur le serveur (jamais committé) ou dans les variables
d'environnement fournies par l'hébergeur, jamais dans le dépôt. Voir la règle "Secrets" de
`CLAUDE.md`.

| Variable | Rôle | Où elle est lue |
| --- | --- | --- |
| `APP_ENV` | `prod` en production | `.env` / serveur |
| `APP_SECRET` | Secret Symfony (CSRF, signatures internes) — générer une vraie valeur aléatoire, ne jamais réutiliser celle de `.env.dev` | `config/packages/framework.yaml` |
| `DATABASE_URL` | Connexion MySQL réelle (utilisateur applicatif dédié, pas root) | `config/packages/doctrine.yaml` |
| `REDIS_URL` | Connexion Redis réelle | `config/packages/cache.yaml` |
| `MAILER_DSN` | DSN SMTP réel — **jamais `null://null` en production** (sinon e-mail de vérification, mot de passe oublié et confirmation de contact ne partent jamais), et jamais le transport Gmail commenté dans `.env` (explicitement marqué "dev only") | `config/packages/mailer.yaml` |
| `MAILER_FROM_ADDRESS` / `MAILER_FROM_NAME` | Expéditeur des e-mails transactionnels | `IdentityMailer`, `ContactMailer` |
| `CONTACT_RECIPIENT_EMAIL` | Boîte de réception réelle du formulaire `/contact` | `ContactMailer`, `LegalController` |
| `GOOGLE_ROUTES_API_KEY` | Clé Google Cloud Console, API Routes activée — c'est un vrai secret facturable, à restreindre par domaine/IP côté Google Cloud Console | `GoogleRoutesProvider` |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe **live** (pas test) une fois prêt à encaisser réellement | `config/packages/stripe.yaml` |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature du webhook Stripe — voir plus bas, dépend de l'URL webhook réelle | `StripeBillingProvider` |
| `EXTENSION_CHROME_ID` | ID public de l'extension Chrome publiée — pas un secret, mais doit être le vrai ID une fois l'extension approuvée sur le Chrome Web Store (voir `chrome-extension/RELEASE_CHECKLIST.md`) ; tant qu'il reste sur le placeholder, le handoff `/account/extensions/connect` ne peut cibler aucune extension réelle en production | `AccountExtensionController` |
| `DEFAULT_URI` | URL de base pour générer des liens hors contexte HTTP (commandes CLI) — mettre le vrai domaine de production | `config/packages/routing.yaml` |

## Étapes de déploiement (premier déploiement)

1. **Cloner le dépôt** sur le serveur, se placer sur `main`.
2. **`.env.local`** avec toutes les vraies valeurs de la table ci-dessus — permissions restrictives (`chmod 600`), jamais dans le dépôt.
3. **Dépendances PHP** : `composer install --no-dev --optimize-autoloader --no-interaction`.
4. **Assets frontend** : `cd assets/app && npm ci && npm run build` (nécessite Node 22 sur le
   serveur, ou construit ailleurs — CI par exemple — puis `public/build/` copié sur le serveur).
   Sans cette étape, toute page appelant `vite_entry_script_tags()`/`vite_entry_link_tags()` casse
   au premier rendu — il n'y a aucune compilation à la volée côté serveur.
5. **Schéma de base de données** — lire attentivement avant d'exécuter :

   Le dépôt contient deux mécanismes en apparence contradictoires : 7 fichiers de migration
   historiques (`migrations/`, datés du 13 août 2026, couvrant les Phases 1 à 7) plus
   `config/packages/doctrine_migrations.yaml` toujours configuré, **et** la règle explicite de
   `CLAUDE.md` depuis la Phase 8 : *"No new Doctrine migrations — sync schema via
   `doctrine:schema:update --force` instead"*. Cette règle ne précise pas explicitement le cas de
   la production, mais elle est présentée comme la pratique du projet dans son ensemble, pas
   seulement dev/test — et c'est la seule pratique activement maintenue depuis la Phase 8 (aucune
   migration n'a été ajoutée depuis). **Recommandation retenue ici** : traiter
   `doctrine:schema:update --force` comme la méthode de synchronisation de schéma également en
   production, cohérente avec le reste du projet, plutôt que de faire vivre deux stratégies en
   parallèle. Sur une base neuve (premier déploiement), `doctrine:schema:update --force` crée
   directement le schéma complet actuel (Phases 1 à 10) — les anciennes migrations n'ont pas besoin
   d'être rejouées.

   ```bash
   php bin/console doctrine:database:create --if-not-exists --env=prod
   php bin/console doctrine:schema:update --dump-sql --env=prod   # à relire avant --force
   php bin/console doctrine:schema:update --force --env=prod
   php bin/console app:credit-pack:seed-launch-grid --env=prod     # une fois, base neuve seulement
   ```

   **`doctrine:schema:update --force` n'a pas de rollback** (contrairement à une vraie migration
   avec `down()`) — toujours relire le `--dump-sql` avant de lancer `--force`, et sauvegarder la
   base avant tout déploiement qui touche une entité, y compris les suivants (pas seulement le
   premier).

6. **Cache Symfony** : `php bin/console cache:clear --env=prod && php bin/console cache:warmup --env=prod`.
7. **Configuration serveur web** — exemple Nginx (à adapter, aucune config n'existe encore dans le
   dépôt) :

   ```nginx
   server {
       listen 443 ssl http2;
       server_name smartgpx.example;
       root /var/www/smartgpx/public;

       location / {
           try_files $uri /index.php$is_args$args;
       }

       location ~ ^/index\.php(/|$) {
           fastcgi_pass unix:/run/php/php8.4-fpm.sock;
           fastcgi_split_path_info ^(.+\.php)(/.*)$;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           fastcgi_param DOCUMENT_ROOT $document_root;
           internal;
       }

       location ~ \.php$ {
           return 404;
       }

       location /build/ {
           expires 1y;
           add_header Cache-Control "public, immutable";
       }
   }
   ```

   Le certificat TLS peut être géré directement dans le panel Infomaniak (Let's Encrypt automatisé
   sur la plupart de leurs offres Cloud Server) ou via `certbot` en ligne de commande selon
   l'offre exacte retenue.

8. **Webhook Stripe** : dans le Dashboard Stripe (mode *live* une fois prêt), créer un endpoint
   pointant vers `https://<domaine-prod>/billing/webhook/stripe` (POST uniquement, route
   `app_billing_webhook_stripe`), copier le secret de signature généré dans
   `STRIPE_WEBHOOK_SECRET`. Le reverse proxy/serveur web ne doit **jamais** altérer le corps brut
   de cette requête (pas de middleware de parsing de body) — la vérification de signature Stripe
   se fait sur le corps exact reçu, dans `BillingProviderInterface::parseWebhookEvent()`.
9. **Extension Chrome** : `EXTENSION_CHROME_ID` ne peut recevoir sa vraie valeur qu'une fois
   l'extension approuvée sur le Chrome Web Store — voir `chrome-extension/RELEASE_CHECKLIST.md`
   pour ce qu'il reste à faire côté extension (c'est un processus de publication séparé, jamais
   hébergé sur ce serveur).
10. **DNS** : pointer le domaine vers le serveur dans le gestionnaire de domaines Infomaniak (ou
    celui utilisé si le domaine n'y est pas enregistré).

## Déploiements suivants (mise à jour)

```bash
git pull
composer install --no-dev --optimize-autoloader --no-interaction
cd assets/app && npm ci && npm run build && cd ../..
php bin/console doctrine:schema:update --dump-sql --env=prod   # relire avant --force
php bin/console doctrine:schema:update --force --env=prod       # seulement si le dump n'est pas vide
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Aucune coupure de service n'est gérée automatiquement (pas de déploiement symlink-based, pas de
bascule bleu/vert) — à mettre en place séparément si une vraie continuité de service est
nécessaire pendant un déploiement.

## Redis — ce qui en dépend réellement

Une seule configuration Redis existe (`config/packages/cache.yaml`, pool `cache.app` via
`REDIS_URL`), mais plusieurs mécanismes s'appuient dessus indirectement :

- Le cache applicatif général (pool Symfony par défaut).
- Le rate limiter (`config/packages/rate_limiter.yaml` — inscription, réinitialisation de mot de
  passe, conversion, analyse d'URL gratuite, contact) : aucun `storage_service` explicite, donc
  hérite du pool `cache.app`.
- Le login throttling (`config/packages/security.yaml`, firewall `main`) : même mécanisme.
- Le cache de résultats/système Doctrine (bloc `when@prod` de `config/packages/doctrine.yaml`).

**Les sessions PHP n'utilisent pas Redis** — handler natif par défaut
(`config/packages/framework.yaml` ne définit aucun `handler_id`/stockage personnalisé). À changer
séparément si plusieurs instances applicatives doivent un jour partager l'état de session.

## CI

`.github/workflows/ci.yml` ne fait que de la QA (cs-fixer, PHPStan, PHPUnit) sur chaque push
`main` et chaque PR — aucun déploiement automatisé n'existe. Étendre ce pipeline (ou en créer un
séparé) pour déployer automatiquement vers Infomaniak est un travail futur, pas encore fait.

## Tâches planifiées

Aucune commande de l'application n'a besoin d'être exécutée en tâche planifiée (cron) aujourd'hui.
Les deux seules commandes custom du projet sont manuelles et ponctuelles :
`app:user:promote-admin` (attribution de ROLE_ADMIN, jamais depuis l'UI — voir
[ADR-007](../decisions/ADR-007-admin-access-control.md)) et
`app:credit-pack:seed-launch-grid` (une fois, sur une base neuve).

## Hypothèses à confirmer

- **Offre Infomaniak exacte** : ce document suppose un accès SSH complet (VPS "Cloud Server" ou
  Public Cloud). Si l'offre retenue est un hébergement web mutualisé classique, revoir en
  particulier : l'installation de Redis (souvent indisponible sur du mutualisé — une alternative
  serait un cache pool non-Redis, ce qui change `config/packages/cache.yaml` et désactive le rate
  limiter/login throttling persistant décrits plus haut), la version de Node.js disponible pour le
  build, et le contrôle du pool PHP-FPM.
- **MySQL et Redis auto-hébergés ou managés** : ce document suppose les deux installés sur le même
  serveur (ou un serveur dédié séparé) sous contrôle direct. Si Infomaniak fournit une base de
  données ou un Redis managés séparément, seule `DATABASE_URL`/`REDIS_URL` change — le reste de la
  procédure reste valable.
- **Sauvegardes** : aucune stratégie de sauvegarde (base de données ou fichiers) n'est documentée
  ici — à définir selon les outils fournis par Infomaniak pour l'offre retenue.
