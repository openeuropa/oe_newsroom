<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

enum NotificationFrequency: string {

  case ON_PUBLICATION = 'On Publication';

  case DAILY = 'Daily';

  case WEEKLY = 'Weekly';

  public function getCode(): int {
    return match ($this) {
      self::ON_PUBLICATION => 2101,
      self::DAILY => 2102,
      self::WEEKLY => 2103,
    };
  }

}
