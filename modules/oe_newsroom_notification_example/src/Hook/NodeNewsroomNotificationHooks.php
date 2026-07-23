<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_notification_example\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom_node\Domain\NodeNotificationService;
use Drupal\oe_newsroom\Exception\Domain\OperationFailure;
use Drupal\oe_newsroom\ExceptionLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hooks to send notifications for node-related events.
 */
class NodeNewsroomNotificationHooks {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    protected readonly NodeNotificationService $nodeNotificationService,
    protected readonly MessengerInterface $messenger,
    #[Autowire(service: 'logger.channel.oe_newsroom_newsletter')]
    protected readonly LoggerInterface $logger,
    protected readonly ExceptionLogger $exceptionLogger,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Hook_ENTITY_TYPE_insert()
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    // In order to allow users to subscribe, we must create an initial
    // notification.
    try {
      $this->nodeNotificationService->notify(
        $node,
        sprintf('Created: %s', $node->getTitle()),
        sprintf('The following page has been created: %s', $node->getTitle()),
        $node->toUrl(),
      );
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, sprintf('Failed to create a notification for node %d in Newsroom, after it was created in Drupal.', $node->id()));
    }
  }

  /**
   * Hook_ENTITY_TYPE_update()
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    // This is an example notification where user may receive notifications when
    // nodes are updated and published.
    if (!$node->isPublished()) {
      return;
    }

    try {
      $this->nodeNotificationService->notify(
        $node,
        sprintf('Updated: %s', $node->getTitle()),
        sprintf('The following page has been updated: %s', $node->getTitle()),
        $node->toUrl(),
      );
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, sprintf('Failed to create a notification for node %d in Newsroom, after it was updated in Drupal.', $node->id()));
    }
  }

  /**
   * Hook_ENTITY_TYPE_delete()
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    try {
      $this->nodeNotificationService->forget($node);
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, sprintf('Failed to delete node %d in Newsroom, after it was deleted in Drupal.', $node->id()));
    }
  }

}
