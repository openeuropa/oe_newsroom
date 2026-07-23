<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

/**
 * Represents the notification frequency options.
 */
enum NotificationFrequency: string {

  case OnPublication = 'On Publication';

  case Daily = 'Daily';

  case Weekly = 'Weekly';

  /**
   * Gets the integer representation to use in subscribe requests.
   */
  public function getCode(): int {
    return match ($this) {
      self::OnPublication => 2101,
      self::Daily => 2102,
      self::Weekly => 2103,
    };
  }

}
