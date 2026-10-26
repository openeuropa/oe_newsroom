<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Explorer;

use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;
use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\oe_newsroom\Endpoint\NodeSubscriptionEndpoints;
use Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending;

/**
 * Contains additional operations available in the API explorer.
 */
#[NewsroomApiExplorer]
class ApiExplorerNodeOperations {

  public function __construct(
    protected readonly NodeSubscriptionEndpoints $nodeSubscriptionEndpoints,
    protected readonly NodeNotificationEndpoints $nodeNotificationEndpoints,
  ) {}

  /**
   * Subscribes an email to a node id, with custom email text.
   *
   * This is only meant to be run from the API explorer form.
   *
   * @param string|int $node_id
   *   The node ID.
   *   This should be an integer, but technically any string is allowed.
   * @param string $email
   *   The email address to subscribe.
   * @param \Drupal\oe_newsroom\Value\NotificationFrequency $frequency
   *   The requested frequency.
   *
   * @return array|\Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending
   *   The result of the subscribe operation.
   */
  public function subscribe(
    string|int $node_id,
    string $email,
    NotificationFrequency $frequency = NotificationFrequency::OnPublication,
  ): array|SubscriptionPending {
    $time = date("Y-m-d H:i:s T");
    return $this->nodeSubscriptionEndpoints->subscribe(
      $node_id,
      $email,
      $frequency,
      new SendCustomizedEmail(
        "Subscribe verify subject ($node_id: $time)",
        "Subscribe verify <em>header</em> ($node_id: $time)",
        "Subscribe verify <em>description</em> ($node_id: $time)",
        "Subscribe verify <em>action description</em> ($node_id: $time)",
        "Subscribe verify <em>action text</em> ($node_id: $time)",
        "Subscribe verify <em>footer</em> ($node_id: $time)",
      ),
      new SendCustomizedEmail(
        "Subscribe success subject ($node_id: $time)",
        "Subscribe success <em>header</em> ($node_id: $time)",
        "Subscribe success <em>description</em> ($node_id: $time)",
        "Subscribe success <em>action description</em> ($node_id: $time)",
        "Subscribe success <em>action text</em> ($node_id: $time)",
        "Subscribe success <em>footer</em> ($node_id: $time)",
      ),
    );
  }

  /**
   * Creates a node notification with auto-generated text.
   *
   * This is only meant to be run from the API explorer form.
   *
   * @param string|int $node_id
   *   The node ID.
   *   This should be an integer, but technically any string is allowed.
   */
  public function createNotification(
    string|int $node_id,
  ): void {
    $time = date("Y-m-d H:i:s T");
    $this->nodeNotificationEndpoints->nodeNotificationCreate(
      17965,
      "Test notification title ($node_id: $time)",
      "Test notification <em>description</em> ($node_id: $time)",
      "https://example.com/notification/" . str_replace(' ', '.', $time),
      $node_id,
      "Test notification <em>node title</em> ($node_id: $time)",
    );
  }

}
