<?php

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;

/**
 * Provides operations to perform in the remote Newsroom instance.
 */
trait NewsroomOperationTrait {

  /**
   * Creates or deletes a node notification topic, to prepare the stage.
   *
   * This should be called in recording mode, before starting the VCR, to set up
   * the scenario on the remote Newsroom server.
   *
   * @param string $node_id
   *   The node id.
   *   Normally this would be an integer-shaped string, but technically the API
   *   allows arbitrary string ids.
   * @param bool $exists
   *   TRUE if the topic should exist, FALSE if not.
   * @param bool $empty
   *   TRUE if the topic should have zero notifications, FALSE to have one.
   */
  protected function prepareNodeNotification(string $node_id, bool $exists = TRUE, bool $empty = FALSE): void {
    $node_notification_endpoints = \Drupal::service(NodeNotificationEndpoints::class);
    $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
    $this->assertFalse($node_notification_endpoints->nodeNotificationExists($node_id));
    if (!$exists) {
      return;
    }
    $node_notification_endpoints->nodeNotificationCreate(
      section_id: $this->newsroomTestValues->nodeNotificationSectionId,
      notification_title: "The title of the notification ($node_id)",
      notification_description: "The description of the notification ($node_id)",
      notification_url: "https://www.example.com/$node_id",
      node_id: $node_id,
      node_title: "The node title ($node_id)",
    );
    $this->assertSame(1, $node_notification_endpoints->nodeNotificationCount($node_id));
    if (!$empty) {
      return;
    }
    $node_notification_endpoints->nodeNotificationDelete($node_id, FALSE);
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));
    $this->assertSame(0, $node_notification_endpoints->nodeNotificationCount($node_id));
  }

}
