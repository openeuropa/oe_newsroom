<?php

namespace Drupal\oe_newsroom\Value;

/**
 * Enum for subscribe statuses.
 *
 * Currently there is only one status that is actually used.
 */
enum SubscribeStatus: string {

  // The subscription is waiting for email confirmation.
  case Pending = 'pending';

}
