<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;

/**
 * Service to access the Newsroom newsletter subscription API.
 */
#[NewsroomApiExplorer]
final class NewsletterSubscriptionEndpoints {

  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Subscribe an email to the newsletters.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param list<string> $svIds
   *   An array of distribution list IDs. The user will get notification when
   *   they are subscribing for these list(s).
   * @param list<string> $relatedSvIds
   *   An array of distribution list IDs. The user will NOT get notification
   *   when they are subscribing for these list(s).
   * @param string|null $language
   *   Specify the language of the subscription (for all services).
   * @param list<string> $topicExtId
   *   An array of Topic IDs, only used for notifications.
   *
   * @return array
   *   Decoded response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation was denied or failed.
   */
  public function subscribe(
    string $email,
    array $svIds = [],
    array $relatedSvIds = [],
    ?string $language = NULL,
    array $topicExtId = [],
  ): array {
    $payload = [
      'subscription' => array_diff_assoc([
        'sv_id' => implode(',', $svIds),
        // @todo Should this be the normalized or original email?
        'email' => $this->apiClient->normalizeEmail($email),
        'language' => $language ?? '',
      ], [
        // Remove empty 'sv_id' parameter to avoid '500 Internal Server Error'.
        'sv_id' => '',
        // Remove empty 'language' parameter to avoid '400 Bad request'.
        'language' => '',
      ]),
    ];

    if (!empty($relatedSvIds)) {
      $payload['subscription']['relatedSv_Id'] = implode(',', $relatedSvIds);
    }
    if (!empty($topicExtId)) {
      $payload['subscription']['topicExtId'] = implode(',', $topicExtId);
    }

    // @todo On 404, check if the newsletter id was not found, or something else was not found.
    return $this->apiClient
      ->post('subscribe', $payload, [$email])
      ->getJsonArray();
  }

  /**
   * Unsubscribes an email from the newsletters.
   *
   * The user will receive a notification when they are successfully
   * unsubscribed.
   *
   * Possible failure responses:
   *   - 400 "Bad request", if no email provided.
   *   - 400 "Bad request", if distribution list is empty.
   *   - 404 "Service $sv_id not found", if service not found.
   *   - 404 "Not found", if email not found.
   *   - 400 "Bad request", if multiple users have the same (capitalized) email.
   *   - 500 "Unable to Unsubscribe!", if an exception occurs.
   *
   * @param non-empty-string $email
   *   Subscriber e-mail address.
   * @param non-empty-string $distribution_list_id
   *   A distribution list ID.
   *
   * @return string
   *   Decoded response data.
   *   Usually this is the message "User unsubscribed!".
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function unsubscribe(
    string $email,
    string $distribution_list_id,
  ): string {
    return $this->apiClient
      ->get(
        'unsubscribe',
        [
          // @todo Should this be the normalized or original email?
          'user_email' => $this->apiClient->normalizeEmail($email),
          'sv_id' => $distribution_list_id,
        ],
        [$email],
      )
      ->getJsonString();
  }

  /**
   * Invokes the '/v1/subscriptions' endpoint to fetch subscriptions for a user.
   *
   * @param string $email
   *   The email to fetch the subscriptions for.
   *
   * @return array<int, array>
   *   Decoded response data.
   *   Contains user subscription records, typically indexed as a list.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation was denied or failed.
   */
  public function subscriptions(string $email): array {
    if (!$email) {
      throw new \InvalidArgumentException(sprintf('Empty email %s provided', var_export($email, TRUE)));
    }
    if (!preg_match('#^.+\@.+\..+#', $email)) {
      // Do not disclose.
      throw new \InvalidArgumentException('Invalid email provided');
    }
    // The endpoint can be used for more operations, but these would be
    // implemented in separate methods.
    return $this->apiClient
      ->get(
        'subscriptions',
        [
          // @todo Should this be the normalized or original email?
          'user_email' => $this->apiClient->normalizeEmail($email),
        ],
        [$email],
      )
      ->map(function (array|string $data): array {
        if (!is_array($data)) {
          throw new \InvalidArgumentException('Expected an array.');
        }
        if (!array_is_list($data)) {
          throw new \InvalidArgumentException(sprintf(
            'Expected a list. Found array keys %s',
            json_encode(array_keys($data)),
          ));
        }
        return $data;
      });
  }

}
