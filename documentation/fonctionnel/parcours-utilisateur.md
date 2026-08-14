# Parcours utilisateur

## Inscription et activation (implémenté, Phase 1)

```
/register (e-mail + mot de passe)
  → compte créé, non vérifié
  → e-mail de confirmation envoyé (lien signé, expirant)
  → clic sur le lien → compte vérifié
  → /login
```

Voir `documentation/fonctionnel/authentification.md` pour le détail des règles (limitation de
débit, cas d'erreur).

## Mot de passe oublié (implémenté, Phase 1)

```
/forgot-password (e-mail)
  → message générique affiché (que le compte existe ou non)
  → si le compte existe : e-mail avec lien de réinitialisation (1h)
  → clic sur le lien → jeton capturé en session → /reset-password
  → nouveau mot de passe → /login
```

## Première conversion gratuite (implémenté, Phase 2)

```
utilisateur connecté, jamais converti (solde affiché sur la page d'accueil)
  → colle un lien Google Maps sur la page d'accueil (mode de transport pré-rempli, modifiable)
  → conversion effectuée sans consommer de crédit supplémentaire (1 crédit de bienvenue)
  → résultat affiché (origine, destination, étapes, distance, durée, nombre de points)
  → GPX téléchargeable
  → prochaine conversion : -1 crédit ; solde à 0 → message clair, aucune conversion débitée
```

Voir `documentation/technique/google-maps-to-gpx.md` et
[ADR-002](../decisions/ADR-002-credit-ledger.md).

## Achat de crédits (implémenté, Phase 4)

```
/pricing → choix d'un pack → paiement Stripe
  → webhook checkout.session.completed → crédits ajoutés (idempotent)
  → utilisateur redirigé, solde visible
```

Voir `documentation/technique/stripe.md` et
[ADR-006](../decisions/ADR-006-billing-provider.md).

## Utilisation d'un outil gratuit (implémenté, Phase 5/6)

```
/tools/... → dépôt d'un fichier (glisser-déposer ou sélection)
  → traitement entièrement local (aucun envoi au serveur)
  → aperçu du résultat
  → téléchargement
```

## Flux extension Chrome (implémenté, Phase 3)

```
Google Maps (itinéraire ouvert) → icône SmartGPX
  → connexion au compte SmartGPX si nécessaire (jeton, pas de mot de passe stocké)
  → itinéraire détecté, crédits restants affichés
  → Export GPX → terminé
```

Vérifié de bout en bout en Chrome réel — voir `chrome-extension/RELEASE_CHECKLIST.md`.
