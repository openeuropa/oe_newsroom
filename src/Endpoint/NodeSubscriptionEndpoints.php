<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\SubscribeStatus;

/**
 * Exposes endpoints related to node subscriptions.
 */
#[NewsroomApiExplorer]
class NodeSubscriptionEndpoints {

  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Gets mail subscriptions.
   *
   * @param string $email
   *   The email to obtain the subscriptions from.
   *
   * @return list<array>
   *   The subscriptions response array.
   */
  public function subscriptions(string $email): array {
    // @todo Should this have sv_id parameter?
    return $this->apiClient
      ->get(
        'subscriptions',
        [
          'user_email' => $email,
        ],
        [$email],
      )
      ->map(function (string|array $data): array {
        if (!is_array($data) || !array_is_list($data)) {
          throw new \InvalidArgumentException('Expected a list.');
        }
        foreach ($data as $index => $record) {
          if (!is_array($record)) {
            throw new \InvalidArgumentException("Expected an array at index $index.");
          }
        }
        return $data;
      });
  }

  /**
   * Calls the /subscribe endpoint to subscribe to a node id.
   *
   * If hard opt-in is enabled, this will only send an email, and not subscribe
   * anything yet.
   *
   * @param string $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The user email.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The subscription frequency.
   * @param string|null $redirect_to
   *   Redirect url for the hard opt-in consent email.
   *   The hard opt-in needs to be enabled on universe level, it is not
   *   controlled by a parameter.
   * @param bool $nomail
   *   TRUE to skip the email confirmation.
   *
   * @return list<array>|\Drupal\oe_newsroom\Value\SubscribeStatus::Pending
   *   One of:
   *   - A list of existing subscriptions, if subscribed.
   *   - An enum value indicating that the subscription is pending.
   */
  public function subscribe(
    string $node_id,
    string $email,
    NotificationFrequency $frequency,
    ?string $redirect_to = NULL,
    bool $nomail = FALSE,
  ): array|SubscribeStatus {
    $payload = [
      'subscription' => [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'email' => $email,
        'frequency' => $frequency->getCode(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => $node_id,
        'nomail' => $nomail,
        ...$redirect_to ? ['redirect_to' => $redirect_to] : [],
      ],
    ];
    return $this->apiClient
      ->post('subscribe', $payload, [$email])
      ->map(function (string|array $data): array|SubscribeStatus {
        if (!is_array($data)) {
          throw new \InvalidArgumentException('Expected an array.');
        }
        if (isset($data['status'])) {
          if ($data['status'] === 'pending_verification') {
            return SubscribeStatus::Pending;
          }
          throw new \InvalidArgumentException('Unexpected status: ' . $data['status']);
        }
        if (!array_is_list($data)) {
          throw new \InvalidArgumentException('Expected a list.');
        }
        return $data;
      });
  }

  /**
   * Unsubscribes from a node, skipping email confirmation.
   *
   * This is suitable e.g. if the user is logged in, or if the user has already
   * confirmed their consent.
   *
   * @param string $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The user email.
   */
  public function unsubscribe(
    string $node_id,
    string $email,
  ): void {
    $this->doUnsubscribe($node_id, $email, NULL);
  }

  /**
   * Requests to unsubscribe from a node, with email confirmation.
   *
   * @param string $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The user email.
   * @param string $redirect_to
   *   The URL to redirect after the unsubcription.
   */
  public function requestUnsubscribe(
    string $node_id,
    string $email,
    string $redirect_to,
  ): void {
    $this->doUnsubscribe($node_id, $email, $redirect_to);
  }

  /**
   * Performs a request to the unsubscribe endpoint.
   *
   * @param string $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The user email.
   * @param string|null $confirm_email_redirect_to
   *   A redirect url for the confirm email, or NULL to subscribe immediately.
   */
  protected function doUnsubscribe(
    string $node_id,
    string $email,
    string|null $confirm_email_redirect_to,
  ): void {
    $payload = [
      'subscription' => [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => $node_id,
        // @todo Should this be the original or the normalized email?
        'email' => $this->apiClient->normalizeEmail($email),
      ],
    ];
    if ($confirm_email_redirect_to !== NULL) {
      $payload['subscription']['request_authentication'] = TRUE;
      $payload['subscription']['redirect_to'] = $confirm_email_redirect_to;
    }
    // The result will be a generic success string like 'Node notification
    // unsubscribed successfully', and can be ignored.
    // The API does not report the "already unsubscribed" case.
    $this->apiClient->post(
      'unsubscribe/node-notification',
      $payload,
      [$email],
    );
  }

}
