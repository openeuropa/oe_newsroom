<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Explorer;

use Drupal\oe_newsroom\Endpoint\NodeNotificationEndpoints;
use Drupal\oe_newsroom\Endpoint\NodeSubscriptionEndpoints;
use Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom\Value\Sentinel\SubscriptionPending;

class ApiExplorerNodeOperations {

  public function __construct(
    protected readonly NodeSubscriptionEndpoints $nodeSubscriptionEndpoints,
    protected readonly NodeNotificationEndpoints $nodeNotificationEndpoints,
  ) {}

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
