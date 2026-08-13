# Base de données

## Environnement local

MySQL 8 et Redis sont supposés déjà installés et lancés localement par le développeur — pas de
Docker Compose (choix délibéré de la Phase 1). Les vraies chaînes de connexion vivent dans
`.env.local` (dev) et `.env.test.local` (tests), tous deux gitignorés ; `.env`/`.env.dev` ne
contiennent que des valeurs placeholder.

```
DATABASE_URL="mysql://<user>:<password>@127.0.0.1:3306/smartgpx?serverVersion=8.0&charset=utf8mb4"
REDIS_URL="redis://127.0.0.1:6379"
```

Deux bases sont nécessaires : `smartgpx` (dev) et `smartgpx_test` (tests, suffixe `_test`
ajouté automatiquement par `config/packages/doctrine.yaml` en environnement `test`).

## Conventions Doctrine

- Mapping par attributs PHP (`#[ORM\...]`), scanné sur tout `src/` (pas de dossier `Entity/`
  imposé — chaque domaine a son propre `Entity/`, voir
  `documentation/technique/architecture.md`).
- `naming_strategy: underscore_number_aware` → tables et colonnes en `snake_case`.
- Charset `utf8mb4` partout (émojis, caractères multi-octets).
- `TimestampableTrait` (`src/Shared/Doctrine/`) fournit `createdAt`/`updatedAt` à toute entité
  qui en a besoin ; la classe utilisatrice doit porter `#[ORM\HasLifecycleCallbacks]`.

## Migrations

Une migration Doctrine par changement de schéma, générée via `bin/console make:migration` puis
relue avant exécution (jamais appliquée sans revue). Exécution :

```
php bin/console doctrine:migrations:migrate            # dev
php bin/console doctrine:migrations:migrate --env=test # test
```

## Cache et rate limiter (Redis)

`config/packages/cache.yaml` adosse le pool `cache.app` à Redis dans tous les environnements, y
compris les tests. C'est un choix déterminant : le rate limiter (voir
`documentation/technique/securite.md`) doit conserver son état entre deux requêtes HTTP d'un même
test fonctionnel, alors que le kernel Symfony est rebooté à chaque requête d'un client de test —
un cache mémoire (`ArrayAdapter`) perdrait cet état à chaque reboot et rendrait les tests de
throttling non fiables. `tests/bootstrap.php` vide la base Redis locale au démarrage de la suite
de tests pour éviter qu'un run précédent n'épuise les quotas du suivant.
