<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_notification_example\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;

/**
 * Hooks to send notifications for node-related events.
 */
class NodeNewsroomNotificationHooks {

  /**
   * Hook_ENTITY_TYPE_insert()
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    // In order to allow users to subscribe, we must create an initial
    // notification.
    try {
      \Drupal::service(NewsroomClientInterface::class)->nodeNotificationCreate(
        // Section is the identifier for the templates to be used.
        // This needs to be changed based on project configured sections in
        // newsroom.
        section_id: '17965',
        notification_title: sprintf('Created: %s', $node->getTitle()),
        notification_description: sprintf('The following page has been created: %s', $node->getTitle()),
        notification_url: $node->toUrl()->setAbsolute()->toString(),
        node_id: $node->id(),
        node_title: $node->getTitle(),
        create_date: (string) \Drupal::time()->getRequestTime(),
      );
      \Drupal::messenger()->addStatus('Notification created successfully.');
    }
    catch (ClientException $e) {
      \Drupal::messenger()->addError(t('An error occurred while processing your the notification creation, please check logs to know more about the issue.'));
      \Drupal::logger('oe_newsroom_newsletter')->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
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
      \Drupal::service(NewsroomClientInterface::class)->nodeNotificationCreate(
        section_id: '17965',
        notification_title: sprintf('Updated: %s', $node->getTitle()),
        notification_description: sprintf('The following page has been updated: %s', $node->getTitle()),
        notification_url: $node->toUrl()->setAbsolute()->toString(),
        node_id: $node->id(),
        node_title: $node->getTitle(),
        create_date: (string) \Drupal::time()->getRequestTime(),
      );
      \Drupal::messenger()->addStatus('Notification created successfully.');
    }
    catch (ClientException $e) {
      \Drupal::messenger()->addError(t('An error occurred while processing your the notification update, please check logs to know more about the issue.'));
      \Drupal::logger('oe_newsroom_newsletter')->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
    }
  }

  /**
   * Hook_ENTITY_TYPE_delete()
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    try {
      \Drupal::service(NewsroomClientInterface::class)->nodeNotificationDelete($node->id());
      \Drupal::messenger()->addStatus('Notification deleted successfully.');
    }
    catch (ClientException $e) {
      \Drupal::messenger()->addError(t('An error occurred while processing your the notification deletion, please check logs to know more about the issue.'));
      \Drupal::logger('oe_newsroom_newsletter')->error('%type thrown while creating a node notification in %function (line %line of %file).', [] + Error::decodeException($e->getPrevious()));
    }
  }

}
