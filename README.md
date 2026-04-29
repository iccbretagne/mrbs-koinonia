# mrbs-koinonia

Plugin MRBS pour déléguer l'authentification à [Koinonia](https://github.com/iccbretagne/koinonia) via SSO.

## Principe

Les utilisateurs se connectent sur Koinonia (Google OAuth). Le cookie de session Auth.js est partagé avec MRBS via le domaine parent. À chaque requête MRBS vérifie le cookie auprès de l'API Koinonia et construit un utilisateur MRBS avec le niveau correspondant.

| Rôle Koinonia | Niveau MRBS |
|---|---|
| Super Admin, Admin | 2 (admin) |
| Ministre, Responsable département, Adjoint | 1 (utilisateur) |
| Autres | 0 (lecture seule) |

## Installation

1. Copier les fichiers dans votre installation MRBS :

```
web/lib/MRBS/Auth/AuthKoinonia.php   → <mrbs>/web/lib/MRBS/Auth/
web/lib/MRBS/Session/SessionKoinonia.php → <mrbs>/web/lib/MRBS/Session/
```

2. Configurer `config.inc.php` (voir [`config.inc.php.example`](config.inc.php.example)).

3. Dans votre `.env` Koinonia, ajouter :

```
MRBS_API_SECRET=votre-secret-partagé
MRBS_URL=https://salles.example.com
MRBS_CHURCH_ID=clxxxxxxxxxxxxxxxxxxxxxxx
ENABLED_MODULES=mrbs
```

## Configuration minimale

```php
define('KOINONIA_BASE_URL',   'https://koinonia.example.com');
define('KOINONIA_API_SECRET', 'votre-secret-partagé');
define('KOINONIA_CHURCH_ID',  'clxxxxxxxxxxxxxxxxxxxxxxx');

$auth['type']    = 'koinonia';
$auth['session'] = 'koinonia';

$cookie_domain = '.example.com';  // domaine parent partagé
```

## Compatibilité

| Plugin | MRBS | PHP | Koinonia |
|---|---|---|---|
| 1.x | 1.12.x | ≥ 8.1 | ≥ 1.2.0 |

> Le plugin s'appuie sur le système de plugins `Auth` / `Session` de MRBS tel qu'il existe depuis la version 1.12. Les versions antérieures de MRBS utilisaient un système différent et ne sont pas supportées.
>
> La version minimale de Koinonia est 1.2.0 car c'est la première à inclure le module `mrbs` et les endpoints `/api/auth/mrbs/*`.

## Prérequis

- PHP ≥ 8.1
- MRBS 1.12.x
- Koinonia ≥ 1.1.13 avec le module `mrbs` activé (`ENABLED_MODULES=mrbs`)
- MRBS et Koinonia servis depuis le même domaine parent (pour le partage de cookie)

## Licence

Apache License 2.0 — voir [Koinonia](https://github.com/iccbretagne/koinonia) pour le contexte.
