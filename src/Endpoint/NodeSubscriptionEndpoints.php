<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Helper\ArrayHelper;
use Drupal\oe_newsroom\Value\EmailAction\DoNotSendEmail;
use Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail;
use Drupal\oe_newsroom\Value\EmailAction\SendDefaultEmail;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending;

/**
 * Exposes endpoints related to node subscriptions.
 */
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
        '/subscriptions',
        [
          // @todo Should this be the normalized or original email?
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
   * @param string|int $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The user email.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The subscription frequency.
   * @param \Drupal\oe_newsroom\Value\EmailAction\SendDefaultEmail|\Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail $verification_email
   *   An instruction for the verification email.
   *   The verification email is only sent if hard opt-in is enabled in the
   *   Newsroom server on universe level. It cannot be enabled or disabled with
   *   an API parameter.
   * @param \Drupal\oe_newsroom\Value\EmailAction\DoNotSendEmail|\Drupal\oe_newsroom\Value\EmailAction\SendDefaultEmail|\Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail $success_email
   *   An instruction for the success email.
   *
   * @return list<array>|\Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending
   *   One of:
   *   - A list of existing subscriptions, if subscribed.
   *   - An enum value indicating that the subscription is pending.
   */
  public function subscribe(
    string|int $node_id,
    string $email,
    NotificationFrequency $frequency,
    SendDefaultEmail|SendCustomizedEmail $verification_email = SendDefaultEmail::Instance,
    DoNotSendEmail|SendDefaultEmail|SendCustomizedEmail $success_email = SendDefaultEmail::Instance,
  ): array|SubscriptionPending {
    $payload = [
      'subscription' => [
        'sv_id' => (string) $this->apiClient->getNodeServiceId(),
        'email' => $email,
        'frequency' => $frequency->getCode(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => (string) $node_id,
        'nomail' => $success_email === DoNotSendEmail::Instance,
        ...ArrayHelper::filter([
          'email_custom' => ArrayHelper::filter([
            'verify' => ($verification_email instanceof SendCustomizedEmail)
              ? $verification_email->getCustomizedValues()
              : [],
            'subscription' => ($success_email instanceof SendCustomizedEmail)
              ? $success_email->getCustomizedValues()
              : [],
          ]),
        ]),
      ],
    ];
    return $this->apiClient
      ->post('/subscribe', $payload, [$email])
      ->map(function (string|array $data): array|SubscriptionPending {
        if (!is_array($data)) {
          throw new \InvalidArgumentException('Expected an array.');
        }
        if (isset($data['status'])) {
          if ($data['status'] === 'pending_verification') {
            return SubscriptionPending::Instance;
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
   * @param string|int $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The email address to unsubscribe.
   */
  public function unsubscribe(
    string|int $node_id,
    string $email,
  ): void {
    $this->doUnsubscribe($node_id, $email, NULL);
  }

  /**
   * Requests to unsubscribe from a node, with email confirmation.
   *
   * @param string|int $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The email address to unsubscribe.
   * @param string $confirm_email_redirect_url
   *   The URL to redirect to when a user clicks the link in the confirm email.
   */
  public function requestUnsubscribe(
    string|int $node_id,
    string $email,
    string $confirm_email_redirect_url,
  ): void {
    $this->doUnsubscribe($node_id, $email, $confirm_email_redirect_url);
  }

  /**
   * Performs a request to the unsubscribe endpoint.
   *
   * @param string|int $node_id
   *   The node ID.
   *   This is meant to be an integer from a Drupal node id, but technically the
   *   API accepts any string.
   * @param string $email
   *   The email address to unsubscribe.
   * @param string|null $confirm_email_redirect_url
   *   A redirect url for the confirm email, or NULL to subscribe immediately.
   */
  protected function doUnsubscribe(
    string|int $node_id,
    string $email,
    string|null $confirm_email_redirect_url,
  ): void {
    $payload = [
      'subscription' => [
        'sv_id' => (string) $this->apiClient->getNodeServiceId(),
        // The endpoint expects the node id as string, not integer.
        'node_id' => (string) $node_id,
        'email' => $email,
      ],
    ];
    if ($confirm_email_redirect_url !== NULL) {
      $payload['subscription']['request_authentication'] = TRUE;
      $payload['subscription']['redirect_to'] = $confirm_email_redirect_url;
    }
    // The result will be a generic success string like 'Node notification
    // unsubscribed successfully', and can be ignored.
    // The API does not report the "already unsubscribed" case.
    $this->apiClient->post(
      '/unsubscribe/node-notification',
      $payload,
      [$email],
    );
  }

  /**
   * Unsubscribes from the entire node service.
   *
   * The operation happens without email confirmation.
   *
   * @param string $email
   *   The email address to unsubscribe.
   */
  public function unsubscribeAll(string $email): void {
    $query = [
      'user_email' => $email,
      'sv_id' => (string) $this->apiClient->getNodeServiceId(),
    ];
    // The result on success will be a generic message, and can be ignored.
    // The API does not report the "already unsubscribed" case.
    $this->apiClient->get(
      '/unsubscribe',
      $query,
      [$email],
    );
  }

}
