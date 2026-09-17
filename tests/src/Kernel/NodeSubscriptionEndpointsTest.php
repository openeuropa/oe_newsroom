<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom\Endpoint\NodeSubscriptionEndpoints;
use Drupal\oe_newsroom\Exception\Api\FailureResponseException;
use Drupal\oe_newsroom\Exception\Api\NotFoundException;
use Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending;
use Drupal\Tests\oe_newsroom\Constraint\AssocValuesMatch;
use Drupal\Tests\oe_newsroom\Helper\CliHelper;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\NewsroomOperationTrait;
use Drupal\Tests\oe_newsroom\Traits\TryAndCatchTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;
use PHPUnit\Framework\Constraint\IsType;

/**
 * Tests the NodeSubscriptionEndpoints class.
 */
class NodeSubscriptionEndpointsTest extends KernelTestBase {

  use LocalTestValuesTrait;
  use NewsroomOperationTrait;
  use TryAndCatchTrait;
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

    $this->initializeNewsroomAndVcrWithTestValues();
  }

  /**
   * Tests node subscription endpoints.
   *
   * In "recording" mode, this needs manual intervention:
   * The developer needs to open their emails and click a verification link.
   *
   * For this same reason, there is only one single test method covering
   * multiple different cases in a single story, to reduce the manual
   * interaction that is required.
   */
  public function testNodeSubscriptionEndpoints(): void {
    $node_subscription_endpoints = \Drupal::service(NodeSubscriptionEndpoints::class);
    $subscriber_email = $this->newsroomTestValues->subscriberEmail;
    $node_id = 'test.1';
    $other_node_id = 'test.other';
    $unknown_node_id = 'test.unknown';
    $empty_node_id = 'test.empty';
    if ($this->isRecording()) {
      $this->prepareNodeNotification($node_id);
      $this->prepareNodeNotification($other_node_id);
      $this->prepareNodeNotification($unknown_node_id, exists: FALSE);
      // Create one node with no notifications. Only the topic exists.
      $this->prepareNodeNotification($empty_node_id, empty: TRUE);

      // Clear subscriptions from previous test runs.
      // The NotFoundException occurs if the email was already unknown.
      try {
        $node_subscription_endpoints->unsubscribeAll($subscriber_email);
      }
      catch (NotFoundException) {
        // Ignore the exception.
      }
    }

    // Start the recording or replay.
    // Use the full method name for the vcr yaml file to read or write.
    $this->startVcr(__METHOD__);

    $this->vcrComment('No subscriptions exist at first.');
    $this->assertSame([], $node_subscription_endpoints->subscriptions($subscriber_email));

    $this->vcrComment('Unsubscribing an unknown user from an existing node will fail.');
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->unsubscribe($node_id, $subscriber_email),
      NotFoundException::class,
      '404 User not found',
    );

    $this->vcrComment('Unsubscribing an unknown user from an unknown node will fail.');
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->unsubscribe($unknown_node_id, $subscriber_email),
      NotFoundException::class,
      '404 User not found',
    );

    $this->vcrComment('Unsubscribing an unknown user from the entire node service will fail.');
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->unsubscribeAll($subscriber_email),
      NotFoundException::class,
      '404 Not found',
    );

    $this->vcrComment("Subscribe to a node id. The subscription will be pending, until the email is verified.");
    $this->assertSame(
      SubscriptionPending::Instance,
      $node_subscription_endpoints->subscribe(
        $node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
        new SendCustomizedEmail(
          'Newsroom test - confirm email address',
          'This is a <em>custom</em> header text',
          'This is a <em>custom</em> description',
          'This is a <em>custom</em> action description',
          'This is a <em>custom</em> action text',
          'This is a <em>custom</em> footer',
          'https://example.com/subscribe#verify',
        ),
        new SendCustomizedEmail(
          'Newsroom test - successful subscription',
          'This is a <em>custom</em> header text for a success message.',
          'This is a <em>custom</em> description for a success message.',
          'This is a <em>custom</em> action description for a success message.',
          'This is a <em>custom</em> action text for a success message.',
          'This is a <em>custom</em> footer for a success message.',
          'https://example.com/subscribe#success',
        ),
      ),
    );

    $this->vcrComment("Unsubscribing from the given node id is still not possible, because the subscription is pending.");
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->unsubscribe($node_id, $subscriber_email),
      FailureResponseException::class,
      '500 Error: No active subscription found for this service',
    );

    $this->vcrComment('Unsubscribing that user from the entire node service is still not possible, because the subscription is pending.');
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->unsubscribeAll($subscriber_email),
      NotFoundException::class,
      '404 Not found',
    );

    $this->vcrComment('Click the confirm link in the verification email.');
    if ($this->isRecording()) {
      $cli_prompt_input = CliHelper::pauseOnCliPrompt(sprintf(
        <<<'EOT'
A verification email was sent to '%s'.
Please open the email in your email client, and click "Confirm Subscription".
Then hit enter to confirm. Or type 'n' to abort the test.

EOT,
        $subscriber_email,
      ));
      $this->assertSame('', trim($cli_prompt_input), 'Test aborted.');
    }

    $this->vcrComment('Load subscriptions for that email.');
    $this->assertThat(
      $node_subscription_endpoints->subscriptions($subscriber_email),
      $one_subscription_exists_constraint = new AssocValuesMatch([
        [
          'email' => $subscriber_email,
          'newsletterId' => (string) $this->newsroomTestValues->nodeNotificationServiceId,
          'subscribedNotificationItemType' => new AssocValuesMatch([
            [
              'name' => new IsType(IsType::TYPE_STRING),
              'id' => new IsType(IsType::TYPE_STRING),
            ],
          ]),
          'subscribedNotificationTopicType' => new AssocValuesMatch([
            [
              'name' => 'The node title (test.1)',
              'externalId' => 'test.1',
            ],
          ]),
        ],
      ]),
    );

    $this->vcrComment('Attempt to subscribe again to the same node.');
    $this->assertThat(
      $node_subscription_endpoints->subscribe(
        $node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
      ),
      $one_subscription_exists_constraint,
    );

    $this->vcrComment('Attempt to subscribe to an unknown node.');
    $this->tryAndCatch(
      fn () => $node_subscription_endpoints->subscribe(
        $unknown_node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
      ),
      NotFoundException::class,
      '404 Not found',
    );

    $this->vcrComment('Attempt to subscribe to a node with zero notifications.');
    $this->assertSame(
      SubscriptionPending::Instance,
      $node_subscription_endpoints->subscribe(
        $empty_node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
      ),
    );

    $this->vcrComment('Attempt to subscribe to a different node.');
    $this->assertSame(
      SubscriptionPending::Instance,
      $node_subscription_endpoints->subscribe(
        $other_node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
        new SendCustomizedEmail(
          'Newsroom test - Subscribe to other node',
        ),
      ),
    );

    $this->vcrComment('Attempt to subscribe again to that different node.');
    $this->assertSame(
      SubscriptionPending::Instance,
      $node_subscription_endpoints->subscribe(
        $other_node_id,
        $subscriber_email,
        NotificationFrequency::Weekly,
        new SendCustomizedEmail(
          $subject = 'Newsroom test - Subscribe to other node, again',
        ),
      ),
    );

    $this->vcrComment('Load subscriptions, again.');
    $this->assertThat(
      $node_subscription_endpoints->subscriptions($subscriber_email),
      $one_subscription_exists_constraint,
    );

    $this->vcrComment("Unsubscribing from the other node id now possible, even though the subscription is pending.");
    $node_subscription_endpoints->unsubscribe($other_node_id, $subscriber_email);

    $this->vcrComment("Unsubscribing is idempotent.");
    $node_subscription_endpoints->unsubscribe($other_node_id, $subscriber_email);

    $this->vcrComment('Click the confirm link in the verification email for the node that was just unsubscribed.');
    if ($this->isRecording()) {
      $cli_prompt_input = CliHelper::pauseOnCliPrompt(sprintf(
        <<<'EOT'
A second verification email was sent to '%s'.
The subject is '%s'.
Please open the email in your email client, and click "Confirm Subscription".
Then hit enter to confirm. Or type 'n' to abort the test.

EOT,
        $subscriber_email,
        $subject,
      ));
      $this->assertSame('', trim($cli_prompt_input), 'Test aborted.');
    }

    $this->vcrComment('Load subscriptions, again. Now there should be two.');
    $this->assertThat(
      $node_subscription_endpoints->subscriptions($subscriber_email),
      new AssocValuesMatch([
        [
          'email' => $subscriber_email,
          'newsletterId' => (string) $this->newsroomTestValues->nodeNotificationServiceId,
          'subscribedNotificationItemType' => new AssocValuesMatch([
            [
              'name' => new IsType(IsType::TYPE_STRING),
              'id' => new IsType(IsType::TYPE_STRING),
            ],
          ]),
          'subscribedNotificationTopicType' => new AssocValuesMatch([
            [
              'name' => 'The node title (test.1)',
              'externalId' => 'test.1',
            ],
            [
              'name' => 'The node title (test.other)',
              'externalId' => 'test.other',
            ],
          ]),
        ],
      ]),
    );

    $this->vcrComment("Unsubscribe from node.other which was just confirmed.");
    $node_subscription_endpoints->unsubscribe($other_node_id, $subscriber_email);

    $this->vcrComment('Load subscriptions. Now there should be one left.');
    $this->assertThat(
      $node_subscription_endpoints->subscriptions($subscriber_email),
      $one_subscription_exists_constraint,
    );

    $this->vcrComment("Unsubscribe from node.other which was just confirmed, again.");
    $node_subscription_endpoints->unsubscribe($other_node_id, $subscriber_email);

    $this->vcrComment("Unsubscribe from the entire node notification service.");
    $node_subscription_endpoints->unsubscribeAll($subscriber_email);

    $this->vcrComment('Load subscriptions. Now there should be zero left.');
    $this->assertSame(
      [],
      $node_subscription_endpoints->subscriptions($subscriber_email),
    );

    $this->endVcr();
  }

}
