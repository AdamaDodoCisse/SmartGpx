# Admin

**Statut : implémenté (Phase 8).**

Décisions et raisonnement détaillés dans
[ADR-007](../decisions/ADR-007-admin-access-control.md) — ce document couvre la mécanique
d'implémentation.

## `src/Admin/`

```
src/Admin/
  Controller/    # 5 contrôleurs, un par écran — présentation uniquement
  Action/        # ComputeAdminMetricsAction — seule lecture n'appartenant à aucun domaine existant
  Metrics/       # AdminMetrics — DTO readonly
```

Toute mutation (promotion admin, ajustement de crédits, CRUD `CreditPack`, log d'échec de
conversion) vit dans le domaine de l'entité mutée (`Identity/Action/`, `Usage/Action/`,
`Billing/Action/`, `Conversion/Action/`), pas dans `Admin/` — voir la règle de placement dans
l'ADR.

## Pagination (`src/Shared/Pagination/`)

`Paginator`/`PaginatedResult` enveloppent le `Doctrine\ORM\Tools\Pagination\Paginator` natif
(déjà présent via `doctrine/orm`, aucune dépendance ajoutée) :

```php
$paginator = Paginator::fromRequestedPage($request->query->getInt('page', 1));
$result = $userRepository->findPageOrderedByCreatedAt($paginator); // PaginatedResult<User>
```

Chaque méthode de repository qui pagine porte une annotation `@var PaginatedResult<Entité>`
inline sur la valeur de retour de `$paginator->paginate($qb)` : PHPStan niveau 8 ne peut pas
déduire le type générique à travers `paginate()` (son paramètre `QueryBuilder` n'est pas
lui-même générique), donc chaque appelant restreint explicitement le type plutôt que de
propager `PaginatedResult<object>` jusqu'aux templates.

## Conversions échouées

`ConversionFailureReason` (enum) miroir des clés `conversion.error.{unsupported_url,
insufficient_credits,route_not_found,provider_unavailable}`. `LogConversionFailureAction` est
appelée dans les 4 branches `catch` de **`ConvertGoogleMapsController::create()`** ET
**`ExtensionConversionController::create()`** (mot pour mot identiques) :

```php
} catch (InvalidGoogleMapsUrlException|UnsupportedGoogleMapsUrlException) {
    $logConversionFailureAction->execute($user, $dto->url, ConversionFailureReason::UNSUPPORTED_URL);
    return $this->errorResponse('conversion.error.unsupported_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
}
// ... même schéma pour insufficient_credits / route_not_found / provider_unavailable
```

Aucune transaction explicite : la réservation de crédit a déjà été relâchée par
`ConvertGoogleMapsToGpxAction` avant que l'exception n'atteigne le contrôleur, donc aucun
invariant financier n'est en jeu ici.

## Formulaires : Symfony Form, pas de HTML brut

`symfony/form` était déjà une dépendance utilisée dans `src/Identity/Form/*` — les formulaires
admin (`CreditAdjustmentFormType`, `CreditPackFormType`) suivent exactement ce même pattern
(`AbstractType<Request>`, `data_class`), avec la protection CSRF intégrée par défaut du
composant. Piège à connaître pour les tests fonctionnels : le préfixe du nom des champs HTML
n'est **pas** dérivé du nom de la classe `Request` mais du nom de la classe `FormType`
elle-même (`CreditPackFormType` → `credit_pack_form[credits]`, jamais
`credit_pack_request[credits]`).

Sur validation échouée (montant ≤ 0, CSRF invalide…), le contrôleur ne redirige jamais — il
réaffiche le même template avec le formulaire lié et ses erreurs, pour ne pas perdre le contexte
de la page (ledger, solde) que la redirection aurait forcé à recharger sans message d'erreur.

## Canonical/hreflang et routes paramétrées (bug corrigé pendant cette phase)

Le bloc canonical/hreflang de `base.html.twig` (Phase 7) appelait `url(canonical_route)` sans
jamais passer les paramètres de route — invisible tant qu'aucune route publique n'avait de
paramètre obligatoire. Les routes admin `/admin/users/{publicId}` en ont un, ce qui a fait
planter le rendu (« Some mandatory parameters are missing ») dès la première page admin réelle.
Corrigé en repassant `app.request.attributes.get('_route_params')` à chaque appel `url()`, et en
n'émettant plus la balise du tout pour les routes qui n'ont pas de `_canonical_route` (routes
non-i18n comme `/admin/*`, à sens unique, sans pendant `.fr` — pas d'alternative de langue à
annoncer pour elles de toute façon).

## Console : première commande custom du projet

`src/Identity/Command/PromoteUserToAdminCommand.php` (`app:user:promote-admin`) est la première
classe `Command` du projet — aucun enregistrement manuel nécessaire (`App\:` est déjà
`autoconfigure: true` dans `config/services.yaml`, donc auto-tag `console.command`).

## Tests

Login pattern identique au reste de la suite : `WebTestCase::createClient()` +
`$client->loginUser($user)`, un helper `createAdminUser()`/`createRegularUser()` privé par
classe de test (pas de fixture partagée dans ce projet). Preuve spécifique demandée pour cette
phase : `tests/Conversion/Controller/ConvertGoogleMapsControllerTest.php` et
`ExtensionConversionControllerTest.php` déclenchent chacune des 4 raisons d'échec via une vraie
requête HTTP contre le vrai contrôleur (`FakeRoutingProvider::queue(...)` pour route_not_found/
provider_unavailable) et vérifient qu'exactement une ligne `ConversionFailure` apparaît avec la
bonne raison — et qu'un rejet CSRF/rate-limit n'en crée aucune.

**Piège rencontré en écrivant ces tests** : `WebTestCase`'s `$client->request()` reboote le
kernel par défaut entre deux requêtes, ce qui réinitialise l'état en mémoire de
`FakeRoutingProvider` (obtenu via `static::getContainer()` puis `->queue(...)`) avant même que la
requête POST suivante ne l'utilise. Il faut appeler `$client->disableReboot()` juste après
`createClient()` dans tout test qui queue un scénario sur le fake puis fait plusieurs requêtes —
même piège déjà rencontré en Phase 4 pour les tests Stripe.
