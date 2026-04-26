<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Domain;

use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Api\NodeNotificationApi;
use Drupal\oe_newsroom\Api\NodeSubscriptionApi;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Domain\OperationDenied;
use Drupal\oe_newsroom\Exception\Domain\OperationError;
use Drupal\oe_newsroom\Exception\Domain\OperationFailure;
use Drupal\oe_newsroom\Value\NotificationFrequency;

/**
 * Provides business operations for node subscription.
 */
class NodeSubscriptionService {

  public function __construct(
    protected readonly NodeSubscriptionApi $nodeSubscriptionApi,
    protected readonly NodeNotificationApi $nodeNotificationApi,
  ) {}

  /**
   * Checks if a node is known in the notification system.
   *
   * If not, then subscribing to that node won't be possible.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Drupal node id.
   *
   * @return bool
   *   TRUE if subscribing to this node is possible, FALSE if not.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   Unable to determine if the node is known.
   *   This generally indicates a broader failure of the subscription system,
   *   not just for this specific node.
   */
  public function nodeAllowsSubscribing(NodeInterface $node): bool {
    try {
      $notifications = $this->nodeNotificationApi->nodeNotificationGet((int) $node->id());
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to check if node allows subscribing.', previous: $e);
    }
    return (bool) $notifications;
  }

  /**
   * Loads subscriptions for an email address.
   *
   * @param string $email
   *   The email to get subscriptions for.
   *
   * @return list<int>
   *   Node ids the email is subscribed to.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   The operation cannot be completed.
   */
  public function fetchSubsribedNodeIds(string $email): array {
    try {
      $response = $this->nodeSubscriptionApi->subscriptions($email);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to fetch subscriptions.', previous: $e);
    }
    $records = $response[0]['subscribedNotificationTopicType'] ?? [];
    $node_ids = array_column($records, 'externalId');
    // Convert all node ids to integer, and fail on unexpected values.
    return array_map(
      static fn (mixed $id): int => match (TRUE) {
        is_int($id) => $id,
        is_string($id) && (string) (int) $id === $id => (int) $id,
        default => throw new OperationError(sprintf("Unexpected value %s for node id found in the response data.", var_export($id, TRUE))),
      },
      $node_ids,
    );
  }

  /**
   * Subscribes an email address.
   *
   * @param int $node_id
   *   The node id to subscribe to.
   * @param string $email
   *   The email address to subscribe.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency|null $frequency
   *   The notification frequency, or NULL to use the existing frequency.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   The operation failed.
   */
  public function subscribe(int $node_id, string $email, ?NotificationFrequency $frequency = NULL): void {
    // Use existing frequency.
    $frequency ??= $this->fetchSubscriptionFrequency($email);
    // Use default frequency.
    // @todo Make the default configurable.
    $frequency ??= NotificationFrequency::ON_PUBLICATION;
    try {
      // @todo Add event here to allow to change parameters.
      $this->nodeSubscriptionApi->subscribe($node_id, $email, $frequency);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to subscribe.', previous: $e);
    }
  }

  /**
   * Unsubscribes an email address.
   *
   * @param int $node_id
   *   The node id to unsubscribe from.
   * @param string $email
   *   The email address to unsubscribe.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   The operation failed.
   */
  public function unsubscribe(int $node_id, string $email): void {
    try {
      $this->nodeSubscriptionApi->unsubscribe($node_id, $email);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to unsubscribe.', previous: $e);
    }
  }

  /**
   * Fetches the subsription frequency stored for the email address.
   *
   * @param string $email
   *   The email address.
   *
   * @return \Drupal\oe_newsroom\Value\NotificationFrequency|null
   *   The frequency, or NULL if no frequency stored for that email.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   The frequency fetching failed, or returned an unexpected result.
   */
  public function fetchSubscriptionFrequency(string $email): ?NotificationFrequency {
    try {
      $subscriptions_response = $this->nodeSubscriptionApi->subscriptions($email);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to fetch frequency', previous: $e);
    }
    return $this->readFrequencyValue($subscriptions_response[0]['frequency'] ?? NULL, $email);
  }

  /**
   * Sets the notification frequency for an email address.
   *
   * @param string $email
   *   The email address.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The new frequency value.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationDenied
   *   The operation was denied, usually because the email has no subscriptions.
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   The operation failed with system malfunction.
   */
  public function setFrequency(string $email, NotificationFrequency $frequency) {
    try {
      $subscribed_ids = $this->fetchSubsribedNodeIds($email);
    }
    catch (OperationFailure $e) {
      // Add additional context.
      throw new OperationError('Failed to set frequency, because fetching subscriptions failed.', previous: $e);
    }
    if (!$subscribed_ids) {
      throw new OperationDenied('Cannot set frequency, because the email is not subscribed to anything.');
    }
    $first_id = reset($subscribed_ids);
    try {
      $this->nodeSubscriptionApi->subscribe($first_id, $email, $frequency);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to set frequency.', previous: $e);
    }
  }

  /**
   * Gets the frequency number from a frequency string.
   *
   * @param string|null $response_frequency_string
   *   The string representation of the frequency in the response.
   * @param string $email
   *   Email address to use in log messages.
   *
   * @return \Drupal\oe_newsroom\Value\NotificationFrequency|null
   *   A frequency, or NULL if the email is not known.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   An unexpected frequency was returned.
   */
  protected function readFrequencyValue(?string $response_frequency_string, string $email): ?NotificationFrequency {
    if ($response_frequency_string === NULL) {
      // The email address has not subscribed before, no frequency is known.
      return NULL;
    }
    $frequency = NotificationFrequency::tryFrom($response_frequency_string);
    if ($frequency === NULL) {
      // Unknown frequency value. The operation cannot complete.
      throw new OperationError('Unexpected frequency value in response.');
    }
    return $frequency;
  }

  /**
   * Gets select options to choose a frequency.
   *
   * @param callable(string): \Drupal\Component\Render\MarkupInterface $t
   *   String translation function.
   *   This should be passed in, so that this service does not depend on the
   *   translation service.
   *
   * @return array<string, \Drupal\Component\Render\MarkupInterface>
   *   Select options.
   */
  public function getFrequencyOptions(callable $t) {
    return [
      NotificationFrequency::ON_PUBLICATION->getCode() => $t('On publication'),
      NotificationFrequency::DAILY->getCode() => $t('Daily'),
      NotificationFrequency::WEEKLY->getCode() => $t('Weekly'),
    ];
  }

}
