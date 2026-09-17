<?php

namespace Drupal\Tests\oe_newsroom\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\Tests\oe_newsroom\Helper\VcrTransform\NewsroomVcrTransform;
use Drupal\Tests\oe_newsroom\NewsroomConfigurationTestTrait;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;

/**
 * Tests the NodeSubscriptionEndpoints class.
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
    'oe_newsroom_newsletter',
    'oe_newsroom_vcr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'oe_newsroom',
      'oe_newsroom_newsletter',
    ]);
  }

  /**
   * Tests node endpoints.
   */
  public function testNodeEndpoints(): void {
    $this->configureClient();
    $this->startVcr(__METHOD__);

    $node_notification_endpoints = \Drupal::service(NodeNotificationEndpoints::class);

    // Normally the "node id" should be an integer value, corresponding to a
    // Drupal node id.
    // The API also accepts string values, this way we can reduce side effects
    // on a test server in recording mode.
    $node_id = 'test.1';
    $test_values = $this->loadNewsroomTestValues($this->isRecording());
    $section_id = $test_values['node_notification_section_id'];

    // Delete the node for a clean start.
    $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
    $this->assertNodeIdUnknown($node_id);

    // Create one notification for the node id.
    // This will create the topic as side effect.
    $this->vcrComment('Create a node notification.');
    $node_notification_endpoints->nodeNotificationCreate(
      section_id: $section_id,
      notification_title: 'The title of the notification',
      notification_description: 'The description of the notification',
      notification_url: 'https://www.example.com',
      node_id: $node_id,
      node_title: 'The node title',
    );

    // Now one notification exists in the list.
    $this->vcrComment('Load node notifications.');
    $get_result = $node_notification_endpoints->nodeNotificationGet($node_id);
    $this->assertSame([0], array_keys($get_result));
    $this->assertSame('The title of the notification', $get_result[0]['title']);
    $this->assertSame(1, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));

    // Clear pending notifications from the topic.
    $this->vcrComment('Delete pending node notifications.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, FALSE);
    $this->assertNodeIdZeroNotifications($node_id);

    $this->vcrComment('Fully delete the node notification topic.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
    $this->assertNodeIdUnknown($node_id);

    // Only end VCR after a complete and successful test.
    $this->endVcr();
  }

  /**
   * Verifies that a node id is known in Newsroom, but has zero notifications.
   *
   * @param string $node_id
   *   The node id as sent to the API.
   */
  protected function assertNodeIdZeroNotifications(string $node_id): void {
    $node_notification_endpoints = \Drupal::service(NodeNotificationEndpoints::class);
    $this->assertSame([], $node_notification_endpoints->nodeNotificationGet($node_id));
    $this->assertSame(0, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertTrue($node_notification_endpoints->nodeNotificationExists($node_id));
  }

  /**
   * Verifies that a node id is unknown in Newsroom.
   *
   * @param string $node_id
   *   The node id as sent to the API.
   */
  protected function assertNodeIdUnknown(string $node_id): void {
    $node_notification_endpoints = \Drupal::service(NodeNotificationEndpoints::class);
    $this->assertSame([], $node_notification_endpoints->nodeNotificationGet($node_id));
    $this->assertSame(0, $node_notification_endpoints->nodeNotificationCount($node_id));
    $this->assertFalse($node_notification_endpoints->nodeNotificationExists($node_id));
  }

  /**
   * Configures the Newsroom client, and sets transformations for the VCR.
   */
  protected function configureClient(): void {
    $test_values = $this->loadNewsroomTestValues($this->isRecording());
    $newsroom_config = $test_values['oe_newsroom_settings'];
    $default_values = $newsroom_config + $test_values;
    if ($this->isRecording()) {
      $this->vcrPack = NewsroomVcrTransform::fnPackRecords($default_values);
    }
    else {
      $this->vcrUnpack = NewsroomVcrTransform::fnUnpackRecords($default_values);
    }
    $newsroom_api_key = $test_values['newsroom_api_private_key'];
    $settings = Settings::getAll();
    $settings['oe_newsroom']['newsroom_api_key'] = $newsroom_api_key;
    new Settings($settings);
    $this->configureNewsroom($newsroom_config);
  }

}
