<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\oe_newsroom\Value\NotificationFrequency;

/**
 * Exposes endpoints related to node subscriptions.
 */
class NodeSubscriptionApi {

  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Gets mail subscriptions.
   *
   * @param string $email
   *   The email to obtain the subscriptions from.
   *
   * @return array
   *   The subscriptions response array.
   */
  public function subscriptions(string $email): array {
    // @todo Should this have sv_id parameter?
    return $this->apiClient->fetchJson('subscriptions', [
      'user_email' => $email,
    ], [$email]);
  }

  /**
   * Subscribes an e-mail to a node notification, skipping email confirmation.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The subscription frequency.
   *
   * @return array
   *   The node subscription details.
   */
  public function subscribe(
    int $node_id,
    string $email,
    NotificationFrequency $frequency,
  ): array {
    return $this->doSubscribe($node_id, $email, $frequency);
  }

  /**
   * Requests to subscribe to a node, with email confirmation.
   *
   * This is suitable if the user is logged in, or if they already confirmed
   * their consent.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The subscription frequency.
   *
   * @return array
   *   The node subscription details.
   *
   * @todo Remove frequency from this.
   */
  public function requestToSubscribe(
    int $node_id,
    string $email,
    NotificationFrequency $frequency,
  ): array {
    return $this->doSubscribe($node_id, $email, $frequency, TRUE);
  }

  /**
   * Subscribes an e-mail to a node notification.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The subscription frequency.
   * @param bool $nomail
   *   TRUE to skip the email confirmation.
   *
   * @return array
   *   Decoded response data.
   *
   * @todo Remove frequency from this.
   * @todo Is the $nomail really about confirm email or about success email?
   */
  protected function doSubscribe(
    int $node_id,
    string $email,
    NotificationFrequency $frequency,
    bool $nomail = FALSE,
  ): array {
    $normalized_email = $this->apiClient->normalizeEmail($email);
    $payload = [
      'subscription' => [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'email' => $email,
        'frequency' => $frequency->getCode(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => (string) $node_id,
        'nomail' => $nomail,
      ],
    ];
    return $this->apiClient->postJson('subscribe', $payload, [$normalized_email]);
  }

  /**
   * Unsubscribes from a node, skipping email confirmation.
   *
   * This is suitable e.g. if the user is logged in, or if the user has already
   * confirmed their consent.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   */
  public function unsubscribe(
    int $node_id,
    string $email,
  ): void {
    $this->doUnsubscribe($node_id, $email, NULL);
  }

  /**
   * Requests to unsubscribe from a node, with email confirmation.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param string $redirect_to
   *   The URL to redirect after the unsubcription.
   */
  public function requestUnsubscribe(
    int $node_id,
    string $email,
    string $redirect_to,
  ): void {
    $this->doUnsubscribe($node_id, $email, $redirect_to);
  }

  /**
   * Performs a request to the unsubscribe endpoint.
   *
   * @param int $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param string|null $confirm_email_redirect_to
   *   A redirect url for the confirm email, or NULL to subscribe immediately.
   */
  protected function doUnsubscribe(
    int $node_id,
    string $email,
    string|null $confirm_email_redirect_to,
  ): void {
    $normalized_email = $this->apiClient->normalizeEmail($email);
    $payload = [
      'subscription' => [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => (string) $node_id,
        // @todo Should this be the original or the normalized email?
        'email' => $normalized_email,
      ],
    ];
    if ($confirm_email_redirect_to !== NULL) {
      $payload['subscription']['request_authentication'] = TRUE;
      $payload['subscription']['redirect_to'] = $confirm_email_redirect_to;
    }
    // The result will be a generic success string like 'Node notification
    // unsubscribed successfully', and can be ignored.
    // The API does not report the "already unsubscribed" case.
    $this->apiClient->postJson(
      'unsubscribe/node-notification',
      $payload,
      [$normalized_email],
    );
  }

}
