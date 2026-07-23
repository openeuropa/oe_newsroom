<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_node\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom_node\Domain\NodeSubscriptionService;
use Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock\NewsroomPlugin;
use Drupal\Tests\oe_newsroom\NewsroomConfigurationTestTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomClientMockTrait;

/**
 * Tests the node subscription service.
 */
class NodeSubscriptionServiceTest extends KernelTestBase {

  use NewsroomClientMockTrait;
  use NewsroomConfigurationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'http_request_mock',
    'oe_newsroom',
    'oe_newsroom_newsletter',
    'oe_newsroom_newsletter_mock',
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

    $settings = Settings::getAll();
    $settings['oe_newsroom']['newsroom_api_key'] = 'phpunit-test-private-key';
    new Settings($settings);
    \Drupal::state()->set(NewsroomPlugin::STAKE_KEY_VALIDATE_UNSUBSCRIPTIONS, FALSE);
  }

  /**
   * Tests the client configuration.
   */
  public function testClientConfiguration(): void {
    $universe = $this->randomMachineName();
    $app_id = $this->randomMachineName();

    $this->configureNewsroom([
      'universe' => $universe,
      'app_id' => $app_id,
      'hash_method' => 'md5',
      'normalised' => FALSE,
      'node_service_id' => '1234',
    ]);

    $service = \Drupal::service(NodeSubscriptionService::class);

    $service->subscribe(777, 'testuser@example.com');
  }

}
