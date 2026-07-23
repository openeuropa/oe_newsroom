<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Domain;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Domain\OperationError;

/**
 * Provides business operations for node notification.
 */
#[NewsroomApiExplorer]
class NodeNotificationService {

  public function __construct(
    protected readonly NodeNotificationEndpoints $nodeNotificationEndpoints,
    protected readonly TimeInterface $time,
    protected readonly int $sectionId,
  ) {}

  /**
   * Creates a new instance with a default section ID.
   *
   * @param \Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints $nodeNotificationEndpoints
   *   The node notification endpoints class.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   *
   * @return static
   *   New instance.
   */
  public static function create(
    NodeNotificationEndpoints $nodeNotificationEndpoints,
    TimeInterface $time,
  ): static {
    return new static(
      $nodeNotificationEndpoints,
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
      $this->nodeNotificationEndpoints->nodeNotificationCreate(
        $this->sectionId,
        $notification_title,
        $notification_description,
        $url->toString(),
        $node->id(),
        $node->getTitle(),
      );
    }
    catch (ApiException $e) {
      // This case also includes NotFoundException, which typically means that
      // the service id is wrong, which counts as misconfiguration.
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
      $this->nodeNotificationEndpoints->nodeNotificationDelete($node->id(), TRUE);
    }
    catch (ApiException $e) {
      // This case also includes NotFoundException, which typically means that
      // the service id is wrong, which counts as misconfiguration.
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
   *   The response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationError
   *
   * @todo This is WIP, currently it is not used anywhere.
   */
  public function fetch(NodeInterface $node): array {
    try {
      $response = $this->nodeNotificationEndpoints->nodeNotificationGet((int) $node->id());
    }
    // @todo Handle NotFoundException separately.
    catch (ApiException $e) {
      throw new OperationError(sprintf('Failed to fetch notifications for node %d', $node->id()), previous: $e);
    }
    // @todo Return a structured response object.
    return $response;
  }

}
