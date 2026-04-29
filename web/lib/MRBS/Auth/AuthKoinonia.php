<?php
declare(strict_types=1);
namespace MRBS\Auth;

use MRBS\User;

/**
 * Authentication backend for Koinonia SSO.
 *
 * Since authentication is handled externally by Koinonia (cookie-based),
 * this class always validates positively — actual identity resolution is
 * done by SessionKoinonia.
 *
 * Usage in config.inc.php:
 *   $auth['type']    = 'koinonia';
 *   $auth['session'] = 'koinonia';
 *
 * Required constants (define before including MRBS):
 *   define('KOINONIA_BASE_URL', 'https://koinonia.example.com');
 *   define('KOINONIA_API_SECRET', 'your-mrbs-api-secret');
 *   define('KOINONIA_CHURCH_ID', 'clxxxxx');   // Koinonia churchId
 */
class AuthKoinonia extends Auth
{
  /**
   * Always validates positively: real auth is handled by SessionKoinonia.
   */
  public function validateUser(
    #[\SensitiveParameter]
    ?string $user,
    #[\SensitiveParameter]
    ?string $pass): string|false
  {
    return $user ?? false;
  }


  /**
   * Fetches fresh user data from Koinonia API.
   */
  protected function getUserFresh(string $username): ?User
  {
    $data = $this->callApi('/api/auth/mrbs/user?username=' . rawurlencode($username));

    if ($data === null) {
      return null;
    }

    $user = new User($username);
    $user->display_name = $data['display_name'] ?? $username;
    $user->email        = $data['email'] ?? null;
    $user->level        = (int) ($data['level'] ?? 0);

    return $user;
  }


  /**
   * Performs a GET request to the Koinonia API.
   *
   * @return array<string,mixed>|null Decoded JSON or null on failure.
   */
  private function callApi(string $path): ?array
  {
    $url = rtrim(KOINONIA_BASE_URL, '/') . $path;

    $context = stream_context_create([
      'http' => [
        'method'  => 'GET',
        'header'  => 'Authorization: Bearer ' . KOINONIA_API_SECRET . "\r\n",
        'timeout' => 3,
        'ignore_errors' => true,
      ],
      'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
      ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
      return null;
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : null;
  }
}
