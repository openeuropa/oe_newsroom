<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom_vcr\Vcr\VcrStore;
use Drupal\node\NodeInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides VCR helpers for node-subscription tests.
 *
 * This trait requires LocalTestValuesTrait.
 */
trait NodeSubscriptionVcrTrait {

  /**
   * Starts a deterministic VCR replay for a failed node subscription.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being subscribed to.
   * @param string $subscriber_email
   *   The email submitted in the form.
   */
  protected function startFailedNodeSubscriptionReplay(NodeInterface $node, string $subscriber_email): void {
    $expected_redirect = $node->toUrl('canonical', [
      'absolute' => TRUE,
      'query' => [
        'newsroom_node_subscribed' => 1,
      ],
    ])->toString();
    \Drupal::service(VcrStore::class)->startReplay([
      new TaggedValue('NewsroomRequest', [
        'method' => 'POST',
        'path' => '/newsroom/api/v1/subscribe',
        'data' => [
          'subscription' => [
            'sv_id' => (string) $this->newsroomTestValues->nodeNotificationServiceId,
            'email' => $subscriber_email,
            'frequency' => NotificationFrequency::OnPublication->getCode(),
            'node_id' => (string) $node->id(),
            'nomail' => FALSE,
            'email_custom' => [
              'verify' => [
                'email_custom_subject' => 'Confirm your subscription',
                'email_custom_redirect' => $expected_redirect,
              ],
              'subscription' => [
                'email_custom_subject' => 'You have been subscribed',
              ],
            ],
          ],
          'key' => new TaggedValue('Capture', '<signature key>'),
          'app' => $this->newsroomTestValues->appId,
        ],
      ]),
      new TaggedValue('Response', [
        'status' => 500,
        'data' => ['error' => 'Simulated failure'],
      ]),
    ]);
  }

  /**
   * Ends a failed node-subscription replay.
   */
  protected function endFailedNodeSubscriptionReplay(): void {
    \Drupal::service(VcrStore::class)->endReplay();
  }

}
