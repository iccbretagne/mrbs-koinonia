<?php
declare(strict_types=1);
namespace MRBS\Session;

use MRBS\User;

/**
 * Session scheme for Koinonia SSO.
 *
 * Reads the Auth.js session cookie set by Koinonia, calls the Koinonia
 * /api/auth/mrbs/session endpoint to resolve the current user, then builds
 * an MRBS User object.  Unauthenticated visitors can browse in read-only mode
 * and see a "Login" button in the MRBS header to connect via Koinonia.
 *
 * Usage in config.inc.php:
 *   $auth['type']    = 'koinonia';
 *   $auth['session'] = 'koinonia';
 *
 * Required constants (define before MRBS includes):
 *   define('KOINONIA_BASE_URL',    'https://koinonia.example.com');
 *   define('KOINONIA_API_SECRET',  'your-mrbs-api-secret');
 *   define('KOINONIA_CHURCH_ID',   'clxxxxx');
 *
 * Optional constants:
 *   define('KOINONIA_COOKIE_NAME', 'authjs.session-token');
 *   // In production Auth.js prefixes the cookie with __Secure-
 *   // Set this to '__Secure-authjs.session-token' for production deployments.
 *
 *   define('KOINONIA_LOGIN_URL', 'https://koinonia.example.com');
 *   // Public URL used for browser redirects (login button, protected pages).
 *   // Set this when KOINONIA_BASE_URL is an internal URL unreachable by browsers.
 *   // Defaults to KOINONIA_BASE_URL if not set.
 */
class SessionKoinonia extends Session
{
  private const DEFAULT_COOKIE = 'authjs.session-token';

  /**
   * Returns the authenticated MRBS user, or null for anonymous read-only access.
   * Redirects to Koinonia login only for pages that require authentication.
   */
  public function getCurrentUser(): ?User
  {
    $token = $this->readSessionToken();

    if ($token === null) {
      if ($this->pageRequiresAuth()) {
        $this->redirectToLogin();
      }
      return null;
    }

    $data = $this->callSessionApi($token);

    if ($data === null) {
      // Token is invalid or Koinonia is unreachable.
      if ($this->pageRequiresAuth()) {
        $this->redirectToLogin();
      }
      return null;
    }

    $user = new User($data['username']);
    $user->display_name = $data['display_name'] ?? $data['username'];
    $user->email        = $data['email'] ?? null;
    $user->level        = (int) ($data['level'] ?? 0);

    return $user;
  }


  /**
   * Returns form params for the "Login" button shown in the MRBS header.
   * Clicking redirects to Koinonia login with the current URL as callbackUrl.
   */
  public function getLogonFormParams(): ?array
  {
    $returnUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
      . ($_SERVER['REQUEST_URI'] ?? '/');

    return [
      'action' => $this->loginBaseUrl() . '/?callbackUrl=' . rawurlencode($returnUrl),
      'method' => 0, // Form::METHOD_GET
    ];
  }


  /**
   * No logout button in MRBS — logout is managed by Koinonia.
   */
  public function getLogoffFormParams(): ?array
  {
    return null;
  }


  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Public URL for browser redirects (login button, protected pages).
   * Uses KOINONIA_LOGIN_URL if defined, falls back to KOINONIA_BASE_URL.
   */
  private function loginBaseUrl(): string
  {
    return defined('KOINONIA_LOGIN_URL')
      ? rtrim(KOINONIA_LOGIN_URL, '/')
      : rtrim(KOINONIA_BASE_URL, '/');
  }


  /**
   * Reads the Auth.js session token from the raw Cookie header.
   *
   * PHP converts '.' to '_' in $_COOKIE keys, so 'authjs.session-token'
   * becomes 'authjs_session-token' and is never found. Reading the raw
   * HTTP_COOKIE header bypasses this sanitization.
   */
  private function readSessionToken(): ?string
  {
    $cookieName = defined('KOINONIA_COOKIE_NAME')
      ? KOINONIA_COOKIE_NAME
      : self::DEFAULT_COOKIE;

    $rawCookies = $_SERVER['HTTP_COOKIE'] ?? '';
    if ($rawCookies === '') {
      return null;
    }

    foreach ([$cookieName, '__Secure-' . $cookieName] as $name) {
      $value = $this->extractCookieValue($rawCookies, $name);
      if ($value !== null) {
        return $value;
      }
    }

    return null;
  }


  /**
   * Extracts a single cookie value from the raw Cookie header string.
   */
  private function extractCookieValue(string $rawCookies, string $name): ?string
  {
    foreach (explode(';', $rawCookies) as $part) {
      $part = trim($part);
      $eq   = strpos($part, '=');
      if ($eq === false) {
        continue;
      }
      $key = trim(substr($part, 0, $eq));
      if ($key === $name) {
        $value = trim(substr($part, $eq + 1));
        return $value !== '' ? $value : null;
      }
    }
    return null;
  }


  /**
   * Calls /api/auth/mrbs/session with the raw cookie token.
   *
   * @return array<string,mixed>|null
   */
  private function callSessionApi(string $token): ?array
  {
    $url = rtrim(KOINONIA_BASE_URL, '/') . '/api/auth/mrbs/session';

    $context = stream_context_create([
      'http' => [
        'method'  => 'GET',
        'header'  => implode("\r\n", [
          'Authorization: Bearer ' . KOINONIA_API_SECRET,
          'X-Mrbs-Session-Token: ' . $token,
          'X-Koinonia-Church-Id: ' . KOINONIA_CHURCH_ID,
        ]) . "\r\n",
        'timeout' => 3,
        'ignore_errors' => true,
      ],
      'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
      ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
      return null;
    }

    // Check HTTP status via $http_response_header (populated by file_get_contents).
    if (isset($http_response_header[0]) &&
        !str_contains($http_response_header[0], '200')) {
      return null;
    }

    $decoded = json_decode($raw, true);

    return (is_array($decoded) && isset($decoded['username'])) ? $decoded : null;
  }


  /**
   * Returns true if the current page requires an authenticated user.
   * MRBS sets $_GET['action'] or uses specific script names for write operations.
   */
  private function pageRequiresAuth(): bool
  {
    $writeActions = [
      'edit', 'del', 'approve', 'reject',
      'save', 'add', 'update', 'delete',
      'report',
    ];

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    return in_array($action, $writeActions, true);
  }


  /**
   * Redirects the browser to the Koinonia login page and exits.
   */
  private function redirectToLogin(): never
  {
    $returnUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
      . ($_SERVER['REQUEST_URI'] ?? '/');

    $target = $this->loginBaseUrl() . '/?callbackUrl=' . rawurlencode($returnUrl);

    header('Location: ' . $target, true, 302);
    exit;
  }
}
