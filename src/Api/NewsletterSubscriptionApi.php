<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

/**
 * Service to access the Newsroom newsletter subscription API.
 */
final class NewsletterSubscriptionApi {

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
    $normalized_email = $this->apiClient->normalizeEmail($email);
    $payload = [
      'subscription' => [
        'universeAcronym' => $this->apiClient->getUniverseAcronym(),
        'topicExtWebsite' => $this->apiClient->getAppId(),
        'sv_id' => implode(',', $svIds),
        // @todo Should this be the normalized or original email?
        'email' => $normalized_email,
        'language' => $language,
      ],
    ];

    if (!empty($relatedSvIds)) {
      $payload['subscription']['relatedSv_Id'] = implode(',', $relatedSvIds);
    }
    if (!empty($topicExtId)) {
      $payload['subscription']['topicExtId'] = implode(',', $topicExtId);
    }

    return $this->apiClient->postJson('subscribe', $payload, [$normalized_email], FALSE);
  }

  /**
   * Unsubscribes an email from the newsletters.
   *
   * The user will receive a notification when they are successfully
   * unsubscribed.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param string $distribution_list_id
   *   An distribution list ID.
   *
   * @return array
   *   Decoded response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   */
  public function unsubscribe(
    string $email,
    string $distribution_list_id,
  ): array {
    $normalized_email = $this->apiClient->normalizeEmail($email);
    return $this->apiClient->fetchJson('unsubscribe', [
      'user_email' => $normalized_email,
      'sv_id' => $distribution_list_id,
    ], [$normalized_email]);
  }

  /**
   * Gets mail subscriptions.
   *
   * @param string $email
   *   The email to obtain the subscriptions from.
   *
   * @return array
   *   Decoded response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation was denied or failed.
   */
  public function subscriptions(string $email): array {
    $normalized_email = $this->apiClient->normalizeEmail($email);
    // The endpoint can be used for more operations, but these would be
    // implemented in separate methods.
    return $this->apiClient->fetchJson('subscriptions', [
      'user_email' => $normalized_email,
    ], [$normalized_email]);
  }

}
