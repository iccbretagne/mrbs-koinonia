# CLAUDE.md — mrbs-koinonia

Contexte pour les agents IA travaillant sur ce projet.

## Projet

**mrbs-koinonia** est un plugin PHP pour [MRBS](https://mrbs.sourceforge.io/) qui délègue l'authentification à une instance [Koinonia](https://github.com/iccbretagne/koinonia) via SSO cookie + API REST.

- **Repository** : https://github.com/iccbretagne/mrbs-koinonia
- **Repo Koinonia associé** : https://github.com/iccbretagne/koinonia

## Architecture

Le plugin ajoute deux classes dans le système de plugins MRBS :

| Fichier | Namespace | Rôle |
|---|---|---|
| `web/lib/MRBS/Auth/AuthKoinonia.php` | `MRBS\Auth` | Implémente `Auth` — valide toujours positivement (auth externe), `getUserFresh()` appelle `/api/auth/mrbs/user` |
| `web/lib/MRBS/Session/SessionKoinonia.php` | `MRBS\Session` | Implémente `Session` — lit le cookie Auth.js, appelle `/api/auth/mrbs/session`, redirige si non authentifié |

Configuration dans `config.inc.php` :
```php
$auth['type']    = 'koinonia';
$auth['session'] = 'koinonia';
```

## Stack technique

| Technologie | Version | Rôle |
|---|---|---|
| PHP | 8.4 | Runtime |
| MRBS | 1.12+ | Application hôte |
| PHPStan | — | Analyse statique (optionnel) |

## Conventions de code

- `declare(strict_types=1)` en tête de chaque fichier PHP
- Namespace complet : `MRBS\Auth\` ou `MRBS\Session\`
- Pas de commentaires qui expliquent le *quoi* — seulement le *pourquoi* quand non évident
- Pas de `var_dump`, `print_r`, `die()` en production
- Timeouts courts sur les appels HTTP (`timeout: 3`) — MRBS appelle `getCurrentUser()` sur chaque requête
- `@file_get_contents()` avec `stream_context_create()` — pas de dépendance cURL (pas toujours disponible)

## Structure du projet

```
mrbs-koinonia/
├── web/lib/MRBS/
│   ├── Auth/
│   │   └── AuthKoinonia.php      # Backend auth
│   └── Session/
│       └── SessionKoinonia.php   # Gestion session SSO
├── config.inc.php.example        # Exemple de configuration MRBS
├── .github/
│   └── workflows/ci.yml          # CI : lint PHP + PHPStan
└── CLAUDE.md
```

## Installation

1. Copier `AuthKoinonia.php` dans `<mrbs>/web/lib/MRBS/Auth/`
2. Copier `SessionKoinonia.php` dans `<mrbs>/web/lib/MRBS/Session/`
3. Ajouter dans `config.inc.php` (voir `config.inc.php.example`)
4. Configurer le partage de cookie de session (même domaine parent)
5. Ajouter `MRBS_API_SECRET`, `MRBS_URL`, `MRBS_CHURCH_ID` dans le `.env` Koinonia

## Variables de configuration

| Constante PHP | Variable Koinonia `.env` | Description |
|---|---|---|
| `KOINONIA_BASE_URL` | — | URL de l'instance Koinonia |
| `KOINONIA_API_SECRET` | `MRBS_API_SECRET` | Secret partagé |
| `KOINONIA_CHURCH_ID` | `MRBS_CHURCH_ID` | churchId Koinonia |
| `KOINONIA_COOKIE_NAME` | — | Nom du cookie Auth.js (optionnel) |

## Endpoints Koinonia utilisés

| Endpoint | Appelé par | Usage |
|---|---|---|
| `GET /api/auth/mrbs/session` | `SessionKoinonia` | Résoudre le token cookie → utilisateur |
| `GET /api/auth/mrbs/user?username=X` | `AuthKoinonia` | Récupérer niveau/email d'un utilisateur par son nom |

Tous protégés par `Authorization: Bearer <MRBS_API_SECRET>`.

## Workflow contributeur

### Conventions de branches

| Préfixe | Usage |
|---|---|
| `feat/<nom>` | Nouvelle fonctionnalité |
| `fix/<nom>` | Correction de bug |
| `chore/<nom>` | Maintenance (release, config) |

Ne jamais pousser directement sur `main`.

### Séquence pour une feature

```bash
git checkout -b feat/ma-feature
# ... développement ...
php -l web/lib/MRBS/**/*.php   # vérification syntaxe
# Ouvrir une PR vers main — CI doit passer avant de merger
```

### Séquence de release

```bash
# 1. Bumper la version dans composer.json (si présent) et CHANGELOG.md
git checkout -b chore/release-vX.Y.Z
git commit -m "chore: release vX.Y.Z"
# PR vers main, merger

# 2. Tagger après merge
git checkout main && git pull
git tag vX.Y.Z && git push origin vX.Y.Z
gh release create vX.Y.Z --title "vX.Y.Z" --notes "..."
```

## Règles pour les agents IA

1. **Lire avant d'écrire** : toujours lire un fichier existant avant de le modifier
2. **Suivre les patterns MRBS** : lire `web/lib/MRBS/Session/SessionNt.php` ou `SessionOmni.php` comme référence
3. **Pas de dépendances externes** : pas de `composer require` sans discussion — MRBS doit rester simple à installer
4. **Timeouts HTTP** : toujours 3 secondes max sur les appels vers Koinonia
5. **`declare(strict_types=1)`** : obligatoire en tête de chaque fichier
6. **Pas de push direct sur main** : toujours passer par une branche et une PR
7. **Toujours créer une GitHub Release** (`gh release create`) lors d'un tag de version

## Pièges connus

1. **Cookie `__Secure-`** : en production Auth.js préfixe le cookie avec `__Secure-`. `SessionKoinonia` essaie les deux variantes automatiquement, mais s'assurer que `KOINONIA_COOKIE_NAME` est correctement défini si nécessaire.

2. **Partage de domaine cookie** : le cookie Auth.js doit être accessible depuis le domaine MRBS. Configurer `$cookie_domain = '.example.com'` dans MRBS et `AUTH_URL` / `NEXTAUTH_URL` correctement dans Koinonia.

3. **`getCurrentUser()` appelé fréquemment** : MRBS appelle cette méthode sur chaque requête. Le timeout HTTP de 3s est critique pour éviter de bloquer l'interface.

4. **`$http_response_header`** : cette variable globale PHP est peuplée par `file_get_contents()` avec un contexte HTTP — elle n'existe pas sans contexte. Toujours vérifier son existence avant de l'utiliser.
