<?php
declare(strict_types=1);
namespace MRBS\Session;

use MRBS\User;

/**
 * Session scheme for Koinonia SSO.
 *
 * Reads the Auth.js session cookie set by Koinonia, calls the Koinonia
 * /api/auth/mrbs/session endpoint to resolve the current user, then builds
 * an MRBS User object.  No login form is shown — unauthenticated visitors
 * are redirected to the Koinonia login page.
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
 */
class SessionKoinonia extends Session
{
  private const DEFAULT_COOKIE = 'authjs.session-token';

  /**
   * Returns the authenticated MRBS user, or null for public anonymous access.
   * Redirects to Koinonia login for pages that require authentication.
   */
  public function getCurrentUser(): ?User
  {
    $token = $this->readSessionToken();

    error_log('[SessionKoinonia] getCurrentUser: token=' . ($token ? 'present(' . strlen($token) . 'chars)' : 'null')
      . ' url=' . ($_SERVER['REQUEST_URI'] ?? '?'));

    if ($token === null) {
      if ($this->pageRequiresAuth()) {
        $this->redirectToLogin();
      }
      return null;
    }

    $data = $this->callSessionApi($token);

    error_log('[SessionKoinonia] callSessionApi result: ' . ($data !== null ? json_encode($data) : 'null'));

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


  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Reads the Auth.js session token from the cookie jar.
   * Tries both the plain name and the __Secure- prefixed variant.
   */
  private function readSessionToken(): ?string
  {
    $cookieName = defined('KOINONIA_COOKIE_NAME')
      ? KOINONIA_COOKIE_NAME
      : self::DEFAULT_COOKIE;

    // Try exact name first, then the __Secure- prefixed variant.
    foreach ([$cookieName, '__Secure-' . $cookieName] as $name) {
      if (!empty($_COOKIE[$name])) {
        return $_COOKIE[$name];
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

    $target = rtrim(KOINONIA_BASE_URL, '/')
      . '/?callbackUrl=' . rawurlencode($returnUrl);

    header('Location: ' . $target, true, 302);
    exit;
  }
}
