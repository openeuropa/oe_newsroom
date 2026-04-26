<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

/**
 * Represents the notification frequency options.
 */
enum NotificationFrequency: string {

  case ON_PUBLICATION = 'On Publication';

  case DAILY = 'Daily';

  case WEEKLY = 'Weekly';

  /**
   * Gets the integer representation to use in subscribe requests.
   */
  public function getCode(): int {
    return match ($this) {
      self::ON_PUBLICATION => 2101,
      self::DAILY => 2102,
      self::WEEKLY => 2103,
    };
  }

}
