<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;

/**
 * Service to access the Newsroom external auth API.
 */
#[NewsroomApiExplorer]
class ExternalAuthEndpoints {

  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Requests a token to be sent by email.
   *
   * @param string $email
   *   The email address.
   * @param string $email_link
   *   The redirect url.
   * @param string $email_text
   *   The link text.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function tokenEmail(
    string $email,
    string $email_link,
    string $email_text,
  ): void {
    // The response on success will just say 'Email sent.'.
    $this->apiClient->post(
      'auth/token',
      [
        'user_email' => $email,
        'sendEmail' => 1,
        'emailLink' => $email_link,
        'emailText' => $email_text,
      ],
      [$email],
    );
  }

  /**
   * Requests a token without an email being sent.
   *
   * @param string $email
   *   The email address.
   *
   * @return array{token: string, expiration_date: string}
   *   Response data containing a token and expiration date.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function tokenNomail(string $email): array {
    return $this->apiClient->post(
      'auth/token',
      [
        'user_email' => $email,
        'sendEmail' => 0,
      ],
      [$email],
    )->map(function (array|string $data): array {
      if (
        !is_array($data) ||
        array_keys($data) !== ['token', 'expiration_date'] ||
        !is_string($data['token']) ||
        !is_string($data['expiration_date'])
      ) {
        // The exception will be caught by ->map().
        throw new \InvalidArgumentException('Expected array{token: string, expiration_date: string}.');
      }
      return $data;
    });
  }

  /**
   * Verifies a token.
   *
   * @param string $email
   *   The email address.
   * @param string $token
   *   The token to verify.
   *
   * @return array|string
   *   Decoded response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function login(
    string $email,
    string $token,
  ) {
    $hash = $this->apiClient->generateComposedKey([$token, $email, $this->apiClient->getAppId()]);
    return $this->apiClient->post(
      'auth/login',
      [
        'user_email' => $email,
        'token' => $hash,
      ],
      [$email],
    )->getJsonData();
  }

}
