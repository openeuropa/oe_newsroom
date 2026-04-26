<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Domain;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Domain\OperationError;

/**
 * Provides business operations for node subscription.
 */
class NodeNotificationService {

  public function __construct(
    protected readonly NodeNotificationEndpoints $nodeNotificationApi,
    protected readonly TimeInterface $time,
    protected readonly int $sectionId,
  ) {}

  public static function create(
    NodeNotificationEndpoints $nodeNotificationApi,
    TimeInterface $time,
  ): static {
    return new static(
      $nodeNotificationApi,
      $time,
      17965,
    );
  }

  /**
   * Creates a notification for node that was created or updated.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that was created or updated.
   * @param string $notification_title
   *   The notfication title.
   * @param string $notification_description
   *   The notfication description.
   * @param \Drupal\Core\Url|null $notification_url
   *   The notfication URL, or NULL to use the node url.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   Failure to create the notification.
   */
  public function notify(
    NodeInterface $node,
    string $notification_title,
    string $notification_description,
    ?Url $notification_url = NULL,
  ): void {
    // Don't mutate a Url object that might be used elsewhere.
    $url = clone ($notification_url ?? $node->toUrl());
    $url->setAbsolute();
    try {
      $this->nodeNotificationApi->nodeNotificationCreate(
        $this->sectionId,
        $notification_title,
        $notification_description,
        $url->toString(),
        $node->id(),
        $node->getTitle(),
      );
    }
    catch (ApiException $e) {
      throw new OperationError(sprintf('Failed to create notification for node %s.', $node->id()), previous: $e);
    }
  }

  /**
   * Deletes notifications for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *   Operation failed with error.
   */
  public function forget(NodeInterface $node): void {
    try {
      $this->nodeNotificationApi->nodeNotificationDelete($node->id());
    }
    catch (ApiException $e) {
      throw new OperationError(sprintf('Failed to forget notifications for node %d', $node->id()), previous: $e);
    }
  }

  /**
   * Fetches notifications for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to fetch notifications.
   *
   * @return array
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   */
  public function fetch(NodeInterface $node): array {
    try {
      $response = $this->nodeNotificationApi->nodeNotificationGet((int) $node->id());
    }
    catch (ApiException $e) {
      throw new OperationError(sprintf('Failed to fetch notifications for node %d', $node->id()), previous: $e);
    }
    return $response;
  }

}
