<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Api\NewsroomConnection;
use Drupal\oe_newsroom\Value\Sentinel\Unauthorized;
use Webmozart\Assert\Assert;

/**
 * Service to access the Newsroom external auth API.
 */
class ExternalAuthEndpoints {

  public function __construct(
    protected readonly ApiClient $apiClient,
    protected readonly NewsroomConnection $connection,
  ) {}

  /**
   * Requests a token to be sent by email.
   *
   * If an account with the given email address does not exist in Newsroom, a
   * temporary account will be created.
   *
   * @param string $email
   *   The email address.
   * @param string $action_url
   *   The destination url for the action link.
   *   The url must have a path, or it will not work.
   * @param string $action_text
   *   The link text for the action link.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function tokenEmail(
    string $email,
    string $action_url,
    string $action_text,
  ): void {
    // A url without path or trailing slash, like 'http://example.com', would
    // cause errors in the current version of Newsroom, so don't even make the
    // request.
    $url_parts = parse_url($action_url);
    if (!is_array($url_parts) || !isset($url_parts['path'])) {
      throw new \InvalidArgumentException(sprintf("Expected a url with path, found '%s'.", $action_url));
    }
    // The response on success will just say 'Email sent.'.
    $this->apiClient->post(
      '/auth/token',
      [
        'user_email' => $email,
        'sendEmail' => 1,
        // The same endpoint also handles the non-email operation, so
        // email-specific request parameters are prefixed with 'email'.
        // The method parameter names are intentionally different, the term
        // 'email' is already in the method name.
        'emailLink' => $action_url,
        'emailText' => $action_text,
      ],
      [$email],
    );
  }

  /**
   * Requests a token without an email being sent.
   *
   * If an account with the given email address does not exist in Newsroom, a
   * temporary account will be created.
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
      '/auth/token',
      [
        'user_email' => $email,
        'sendEmail' => 0,
      ],
      [$email],
    )->map(function (array|string $data): array {
      Assert::isArray($data);
      Assert::string($data['token'] ?? NULL);
      Assert::regex($data['expiration_date'] ?? NULL, '#^\d\d\d\d-\d\d-\d\dT\d\d:\d\d:\d\d\.\d+Z$#');
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
   * @return array{user_email: string, user_id: positive-int}|\Drupal\oe_newsroom\Value\Sentinel\Unauthorized
   *   The response data, with email and user id, or Unauthorized if the token
   *   was not accepted.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request failed in an unexpected way.
   */
  public function login(
    string $email,
    string $token,
  ): array|Unauthorized {
    // The newsroom-php code indicates that email and app id are normalized.
    // In this case it uses strtolower() instead of mb_strtolower().
    // @todo Discuss if this should be changed in newsroom-php.
    $hash = $this->apiClient->generateComposedKey(
      $this->connection->normalised
        ? [$token, strtolower($email), strtolower($this->apiClient->getAppId())]
        : [$token, $email, $this->apiClient->getAppId()]
    );
    $api_response = $this->apiClient->post(
      '/auth/login',
      [
        'user_email' => $email,
        'token' => $hash,
      ],
      // Omit the usual 'key' parameter for authentication.
      // The 'token' is enough.
      [],
      assert_success: FALSE,
    );
    // Do not throw an exception on "Unauthorized".
    // Instead, return a sentinel value.
    if ($api_response->getStatusCode() === 401) {
      return Unauthorized::Instance;
    }
    return $api_response
      ->assertSuccess()
      ->map(function (string|array $data) use ($email): array {
        Assert::isArray($data);
        Assert::same($email, $data['user_email'] ?? NULL);
        Assert::positiveInteger($data['user_id'] ?? NULL);
        return $data;
      });
  }

}
