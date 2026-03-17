<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

/**
 * Represents a node subscription.
 */
class NodeSubscription {

  /**
   * Constructor.
   *
   * @param string $name
   *   The node title as reported by Newsroom.
   * @param int $id
   *   The node id.
   */
  public function __construct(
    public readonly string $name,
    public readonly int $id,
  ) {}

}
