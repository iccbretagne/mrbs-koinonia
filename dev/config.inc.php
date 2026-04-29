<?php
declare(strict_types=1);
namespace MRBS;

// ── Base de données MRBS ───────────────────────────────────
$timezone    = "Europe/Paris";
$dbsys       = "mysql";
$db_host     = "db";
$db_database = "mrbs";
$db_login    = "mrbs";
$db_password = "mrbs";
$db_tbl_prefix = "mrbs_";
$db_persist  = false;

// ── Koinonia SSO ───────────────────────────────────────────
// KOINONIA_BASE_URL : depuis le conteneur Docker, Koinonia tourne
// sur le host → utiliser host.docker.internal (Mac/Windows)
// ou 172.17.0.1 (Linux).
define('KOINONIA_BASE_URL',   getenv('KOINONIA_BASE_URL')   ?: 'http://host.docker.internal:3000');
define('KOINONIA_API_SECRET', getenv('KOINONIA_API_SECRET') ?: 'change-me');
define('KOINONIA_CHURCH_ID',  getenv('KOINONIA_CHURCH_ID')  ?: '');

// En dev HTTP, le cookie s'appelle authjs.session-token (sans __Secure-)
// define('KOINONIA_COOKIE_NAME', 'authjs.session-token');

$auth['type']    = 'koinonia';
$auth['session'] = 'koinonia';

// Pas de restriction de domaine en local (localhost)
$cookie_domain = '';
$cookie_path   = '/';
