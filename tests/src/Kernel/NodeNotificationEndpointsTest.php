<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\Tests\oe_newsroom\Constraint\AssocValuesMatch;
use Drupal\Tests\oe_newsroom\NewsroomConfigurationTestTrait;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;

/**
 * Tests the NodeNotificationEndpoints class.
 */
class NodeNotificationEndpointsTest extends KernelTestBase {

  use LocalTestValuesTrait;
  use NewsroomConfigurationTestTrait;
  use VcrTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom',
    'oe_newsroom_vcr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'oe_newsroom',
    ]);
  }

  /**
   * Tests node endpoints.
   */
  public function testNodeEndpoints(): void {
    $this->initializeNewsroomAndVcrWithTestValues();
    $node_notification_endpoints = \Drupal::service(NodeNotificationEndpoints::class);

    // Normally the "node id" should be an integer value, corresponding to a
    // Drupal node id.
    // The API also accepts string values, this way we can reduce side effects
    // on a test server in recording mode.
    $node_id = 'test.1';

    // When working with a real Newsroom server, we need to make sure there is
    // a clean starting point, to make the test behave the same every time.
    // This part is not recorded in the VCR, because it might be different.
    if ($this->isRecording()) {
      // Delete the nodes, if they exist, for a clean start.
      $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
      // Make sure they are gone.
      $this->assertFalse($node_notification_endpoints->nodeNotificationExists($node_id));
    }

    // Start the recording or replay.
    // Use the full method name for the vcr yaml file to read or write.
    $this->startVcr(__METHOD__);

    // Create one notification for the node id.
    // This will create the topic as side effect.
    $this->vcrComment('Create a node notification.');
    $node_notification_endpoints->nodeNotificationCreate(
      section_id: $this->newsroomTestValues->nodeNotificationSectionId,
      notification_title: 'The title of the notification',
      notification_description: 'The description of the notification',
      notification_url: 'https://www.example.com',
      node_id: $node_id,
      node_title: 'The node title',
    );

    // Now one notification exists in the list.
    $this->vcrComment('Load node notifications.');
    $this->assertThat(
      $node_notification_endpoints->nodeNotificationGet($node_id),
      new AssocValuesMatch([
        [
          'title' => 'The title of the notification',
          'topics' => [
            // The first topic is just a generic topic for all node
            // notifications.
            // The second topic represents the node.
            1 => [
              'name' => 'The node title',
            ],
          ],
        ],
      ]),
    );
    $this->assertSame(1, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));

    // Create another notification for the same node id.
    // Pass modified values, to see how this changes the response.
    $this->vcrComment('Create another node notification for the same id.');
    $node_notification_endpoints->nodeNotificationCreate(
      section_id: $this->newsroomTestValues->nodeNotificationSectionId,
      notification_title: 'The title of the notification (modified)',
      notification_description: 'The description of the notification (modified)',
      notification_url: 'https://www.example.com/modified',
      node_id: $node_id,
      node_title: 'The node title (modified)',
    );

    // Now two notifications exists in the list.
    $this->vcrComment('Load node notifications, expecting two.');
    // The order of notifications in the response is not deterministic.
    $notifications = $node_notification_endpoints->nodeNotificationGet($node_id);
    array_multisort(array_column($notifications, 'title'), $notifications);
    $this->assertThat(
      $notifications,
      new AssocValuesMatch([
        [
          'title' => 'The title of the notification',
          'topics' => [
            // The first topic is just a generic topic for all node
            // notifications.
            // The second topic represents the node.
            1 => [
              // The node title is not changed.
              'name' => 'The node title',
            ],
          ],
        ],
        [
          'title' => 'The title of the notification (modified)',
          'topics' => [
            1 => [
              // The node title is not changed.
              'name' => 'The node title',
            ],
          ],
        ],
      ]),
    );
    $this->assertSame(2, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));

    $this->vcrComment('Delete pending node notifications, without deleting the topic.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, FALSE);

    $this->vcrComment('The notification count is zero, but the topic still exists.');
    $this->assertSame([], $node_notification_endpoints->nodeNotificationGet($node_id));
    $this->assertSame(0, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));

    $this->vcrComment('Fully delete the node notification topic.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
    $this->vcrComment('The notification topic has been fully removed.');
    $this->assertFalse($node_notification_endpoints->nodeNotificationExists($node_id));

    // Only end VCR after a complete and successful test.
    $this->endVcr();

    if (!$this->isRecording()) {
      // Assert captured authentication hashes.
      // Hashes that are used multiple times are only collected once.
      // By doing this in a single assertion at the end of the test, a developer
      // can update the hashes all at once.
      $this->assertVcrCaptured(
        [
          '<signature key 0>',
          '<signature key 1>',
          '<signature key 2>',
          '<signature key 3>',
        ],
        [
          // Hash for '/node-notification/create'.
          '562290d782b30bc83c551bac24f76a43',
          // Hash for '/node-notification/get', '*/exists' and '*/count'.
          '50d3359dff6d7b43a24e21f3991df2a9',
          // Hash for the second request to '/node-notification/create', with
          // different parameters.
          'b25f550808bfbdcb2cd64cf38ae0f67d',
          // Hash for '/node-notification/delete'.
          '09616039d3598c952b63f72c96c96054',
        ],
      );
    }
  }

}
