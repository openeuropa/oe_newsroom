<?php

namespace Drupal\Tests\oe_newsroom\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\oe_newsroom\Endpoint\NodeSubscriptionEndpoints;
use Drupal\oe_newsroom\Exception\Api\FailureResponseException;
use Drupal\oe_newsroom\Exception\Api\NotFoundException;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\SubscribeStatus;
use Drupal\Tests\oe_newsroom\Helper\CliHelper;
use Drupal\Tests\oe_newsroom\Helper\VcrTransform\NewsroomVcrTransform;
use Drupal\Tests\oe_newsroom\NewsroomConfigurationTestTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;

/**
 * Tests the NodeSubscriptionEndpoints class.
 */
class NodeNotificationEndpointsTest extends KernelTestBase {

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
    $node_subscription_endpoints = \Drupal::service(NodeSubscriptionEndpoints::class);

    // Normally the "node id" should be an integer value, corresponding to a
    // Drupal node id.
    // The API also accepts string values, this way we can reduce side effects
    // on a test server in recording mode.
    $node_id = 'test.1';
    $test_values = $this->loadNewsroomTestValues();
    $section_id = $test_values['node_notification_section_id'];

    // The main email is one controlled by the developer when recording.
    // The "other" email is always the same, and not controlled by anybody.
    $email = $test_values['test_email'];
    $other_email = 'other@example.com';

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

    // Subscribing to the topic is possible.
    $this->vcrComment('Attempt subscribing the other email.');
    $this->doTestSubscribing($node_id, $other_email, NULL);

    // Clear pending notifications from the topic.
    $this->vcrComment('Delete pending node notifications.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, FALSE);
    $this->assertNodeIdZeroNotifications($node_id);

    // Subscribing to the topic is still possible.
    $this->vcrComment('Test subscribing other email while notification list is empty.');
    $this->doTestSubscribing($node_id, $other_email, TRUE);

    $this->vcrComment('Test subscribing the main email.');
    $this->doTestSubscribing($node_id, $email, NULL);

    // The developer confirms the subscription.
    $this->vcrComment('Click the confirm link in the opt-in email.');
    if ($this->isRecording()) {
      $this->assertSame('', trim(CliHelper::pauseOnCliPrompt(
        message: sprintf(
          <<<'EOT'
An opt-in confirmation email was sent to '%s'.
Please open the email in your email client, and click "Confirm Subscription".
Then hit enter to confirm. Or type 'n' to abort the test.
EOT,
        $email,
      ))), 'Test aborted.');
    }

    // Now subscriptions exist.
    $this->vcrComment('Load subscriptions for that email.');
    $subscriptions = $node_subscription_endpoints->subscriptions($email);
    $this->assertSame([0], array_keys($subscriptions));
    $this->assertSame($email, $subscriptions[0]['email'], Yaml::encode($subscriptions));

    $this->vcrComment('Unsubscribe.');
    $node_subscription_endpoints->unsubscribe($node_id, $email);

    $this->vcrComment('Load subscriptions for that email, after unsubscribe.');
    $subscriptions = $node_subscription_endpoints->subscriptions($email);
    $this->assertSame([], $subscriptions);

    $this->vcrComment('Fully delete the node notification topic.');
    $node_notification_endpoints->nodeNotificationDelete($node_id, TRUE);
    $this->assertNodeIdUnknown($node_id);

    $this->vcrComment('Test subscribing while notification list does not exist.');
    $this->doTestSubscribingNotPossible($node_id, $email);

    // Only end VCR after a complete and successful test.
    $this->endVcr();
  }

  /**
   * Tests requesting a subscription.
   *
   * @param string $node_id
   *   The node id.
   * @param string $email
   *   The email address to subscribe.
   * @param bool|null $account_exists
   *   TRUE if the account already exists in Newsroom.
   *   FALSE if the account does not exist in Newsroom.
   *   NULL if it is not known whether the account exists in Newsroom.
   */
  protected function doTestSubscribing(string $node_id, string $email, ?bool $account_exists): void {
    $node_subscription_endpoints = \Drupal::service(NodeSubscriptionEndpoints::class);

    $this->vcrComment("Fetch subscriptions. The result is empty.");
    $subscriptions_result = $node_subscription_endpoints->subscriptions($email);
    $this->assertSame([], $subscriptions_result);

    // Unsubscribing is not possible until one is subscribed.
    $this->doTestCannotUnsubscribe($node_id, $email, $account_exists);

    $this->vcrComment("Subscribe to a node id. The result will be pending.");
    $subscribe_result = $node_subscription_endpoints->subscribe($node_id, $email, NotificationFrequency::Weekly, 'https://example.com/subscribe');
    $this->assertSame(SubscribeStatus::Pending, $subscribe_result);

    // The user can still not unsubscribe.
    $this->vcrComment("Unsubscribing from the given node id is still not possible, because the subscription is pending.");
    $this->doTestCannotUnsubscribe($node_id, $email, TRUE);
  }

  /**
   * Tests a failed attempt to unsubscribe.
   *
   * @param string $node_id
   *   The node id.
   * @param string $email
   *   The email address to subscribe.
   * @param bool|null $account_exists
   *   TRUE if the account already exists in Newsroom.
   *   FALSE if the account does not exist in Newsroom.
   *   NULL if it is not known whether the account exists in Newsroom.
   */
  protected function doTestCannotUnsubscribe(string $node_id, string $email, ?bool $account_exists): void {
    $node_subscription_endpoints = \Drupal::service(NodeSubscriptionEndpoints::class);
    $this->vcrComment("Attempt to unsubscribe from a node id. An exception will be thrown.");
    try {
      $node_subscription_endpoints->unsubscribe($node_id, $email);
      $this->fail('Expected exception not thrown.');
    }
    catch (NotFoundException $e) {
      // No user account exists for this email address.
      $this->assertSame('404 User not found', $e->getMessage());
      $this->assertFalse($account_exists ?? FALSE);
    }
    catch (FailureResponseException $e) {
      // The user account exists in Newsroom, but nothing can be unsubscribed.
      $this->assertSame('500 Error: No active subscription found for this service', $e->getMessage());
      $this->assertTrue($account_exists ?? TRUE);
    }
  }

  /**
   * Tests a subscribe request when subscribing is not possible for a node id.
   *
   * @param string $node_id
   *   The node id.
   * @param string $email
   *   The email address.
   */
  protected function doTestSubscribingNotPossible(string $node_id, string $email): void {
    $node_subscription_endpoints = \Drupal::service(NodeSubscriptionEndpoints::class);
    try {
      // It is not possible to subscribe to a deleted node notification.
      $node_subscription_endpoints->subscribe(
        node_id: $node_id,
        email: $email,
        frequency: NotificationFrequency::Weekly,
        redirect_to: 'https://example.com/subscribe-redirect',
        nomail: FALSE,
      );
      $this->fail('Expected a NotFoundException to be thrown.');
    }
    catch (NotFoundException) {
      $this->addToAssertionCount(1);
    }
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
    $test_values = $this->loadNewsroomTestValues();
    $newsroom_config = $test_values['oe_newsroom_settings'];
    if ($this->isRecording()) {
      $this->vcrPack = NewsroomVcrTransform::fnPackRecords($newsroom_config + $test_values);
    }
    else {
      $this->vcrUnpack = NewsroomVcrTransform::fnUnpackRecords($newsroom_config + $test_values);
    }
    $newsroom_api_key = $test_values['newsroom_api_private_key'];
    $settings = Settings::getAll();
    $settings['oe_newsroom']['newsroom_api_key'] = $newsroom_api_key;
    new Settings($settings);
    $this->configureNewsroom($newsroom_config);
  }

  /**
   * Loads test values.
   *
   * phpcs:disable Drupal.Commenting.FunctionComment.ReturnCommentIndentation
   * @return array{
   *   oe_newsroom_settings: array,
   *   newsroom_api_private_key: string,
   *   node_notification_section_id: int,
   *   test_email: string,
   * }
   *   Values to use in the test.
   */
  protected function loadNewsroomTestValues(): array {
    if (!$this->isRecording()) {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.yml.dist';
    }
    else {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.yml';
    }
    $this->assertFileExists($test_values_file, 'Please copy `test-values.yml.dist` to `test-values.yml`, and replace the values to connect to a real Newsroom sandbox.');
    $this->assertFileIsReadable($test_values_file);
    $test_values_yaml = file_get_contents($test_values_file);
    $test_values = Yaml::decode($test_values_yaml);
    $this->assertIsArray($test_values['oe_newsroom_settings']);
    $this->assertIsString($test_values['oe_newsroom_settings']['node_service_id']);
    $test_values['newsroom_api_private_key'] ??= getenv(
      $test_values['newsroom_api_private_key_env_name'] ?? 'NEWSROOM_API_PRIVATE_KEY',
    );
    return $test_values;
  }

}
