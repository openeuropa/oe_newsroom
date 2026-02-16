<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_notification_example\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;
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
    protected readonly NewsroomClientInterface $newsroomClient,
    protected readonly MessengerInterface $messenger,
    #[Autowire('logger.channel.oe_newsroom_newsletter')]
    protected readonly LoggerInterface $logger,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Hook_ENTITY_TYPE_insert()
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    // In order to allow users to subscribe we must create a initial notification.
    try {
      $this->newsroomClient->nodeNotificationCreate(
        // Section is the identifier for the templates to be used.
        // This needs to be changed based on project configured sections in
        // newsroom.
        section_id: '17965',
        notification_title: sprintf('Created: %s', $node->getTitle()),
        notification_description: sprintf('The following page has been created: %s', $node->getTitle()),
        notification_url: $node->toUrl()->setAbsolute()->toString(),
        node_id: $node->id(),
        node_title: $node->getTitle(),
        create_date: (string) $this->time->getRequestTime(),
      );
      $this->messenger->addStatus('Notification created successfully.');
    }
    catch (ClientException $e) {
      $this->messenger->addError(t('An error occurred while processing your the notification creation, please check logs to know more about the issue.'));
      $this->logger->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
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
      $this->newsroomClient->nodeNotificationCreate(
        section_id: '17965',
        notification_title: sprintf('Updated: %s', $node->getTitle()),
        notification_description: sprintf('The following page has been updated: %s', $node->getTitle()),
        notification_url: $node->toUrl()->setAbsolute()->toString(),
        node_id: $node->id(),
        node_title: $node->getTitle(),
        create_date: (string) $this->time->getRequestTime(),
      );
      $this->messenger->addStatus('Notification created successfully.');
    }
    catch (ClientException $e) {
      $this->messenger->addError(t('An error occurred while processing your the notification update, please check logs to know more about the issue.'));
      $this->logger->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
    }
  }

  /**
   * Hook_ENTITY_TYPE_delete()
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    try {
      $this->newsroomClient->nodeNotificationDelete($node->id());
      $this->messenger->addStatus('Notification deleted successfully.');
    }
    catch (ClientException $e) {
      $this->messenger->addError(t('An error occurred while processing your the notification deletion, please check logs to know more about the issue.'));
      $this->logger->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
    }
  }

}
