<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\Crypt;

/**
 * Service to manage access tokens.
 */
final class TokenManager {

  /**
   * Defines the maximum time for a token to be valid, one day.
   */
  const EXPIRED_MAX_TIME = 86400;

  public function __construct(
    protected Connection $connection,
    protected TimeInterface $time,
  ) {}

  /**
   * Creates a new token for the given e-mail.
   *
   * If an entry already exists, it generates a new token and refreshed the
   * duration.
   *
   * @param string $mail
   *   The e-mail.
   *
   * @return string
   *   The token.
   */
  public function get(string $mail): string {
    $hash = Crypt::randomBytesBase64();

    if ($this->exists($mail)) {
      $this->connection->update('oe_newsroom_management_access_tokens')
        ->fields([
          'hash' => $hash,
          'changed' => $this->time->getRequestTime(),
        ])
        ->condition('mail', $mail)
        ->execute();

      return $hash;
    }

    // Create new entry.
    $this->connection->insert('oe_newsroom_management_access_tokens')
      ->fields([
        'mail' => $mail,
        'hash' => $hash,
        'changed' => $this->time->getRequestTime(),
      ])->execute();

    return $hash;
  }

  /**
   * Deletes a token.
   *
   * @param string $mail
   *   The e-mail.
   *
   * @return bool
   *   Operation result.
   */
  public function delete(string $mail): bool {
    $query = $this->connection->delete('oe_newsroom_management_access_tokens')
      ->condition('mail', $mail);

    // If any rows were deleted.
    return !empty($query->execute());
  }

  /**
   * Checks if a mail has a token associated.
   *
   * @param string $mail
   *   The e-mail.
   *
   * @return bool
   *   Operation result.
   */
  private function exists(string $mail): bool {
    $query = $this->connection->select('oe_newsroom_management_access_tokens', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->countQuery();

    return !empty((int) $query->countQuery()->execute()->fetchField());
  }

  /**
   * Checks a token is valid for the given e-mail and scope.
   *
   * @param string $mail
   *   The e-mail.
   * @param string $hash
   *   The token to validate.
   *
   * @return bool
   *   Whether the token is valid or not.
   */
  public function isValid(string $mail, string $hash): bool {
    // The subscription exists and is not expired.
    $query = $this->connection->select('oe_newsroom_management_access_tokens', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.hash', $hash)
      ->condition('s.changed', $this->time->getRequestTime() - TokenManager::EXPIRED_MAX_TIME, '>=');

    return !empty((int) $query->countQuery()->execute()->fetchField());
  }

  /**
   * Delete all expired subscriptions.
   */
  public function deleteExpired(): void {
    $this->connection->delete('oe_newsroom_management_access_tokens')
      ->condition('changed', $this->time->getRequestTime() - TokenManager::EXPIRED_MAX_TIME, '<')
      ->execute();
  }

}
