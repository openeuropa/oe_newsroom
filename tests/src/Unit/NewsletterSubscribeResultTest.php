<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Unit;

use Drupal\oe_newsroom\Value\NewsletterSubscribeResult;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Newsletter subscribe result.
 */
class NewsletterSubscribeResultTest extends UnitTestCase {

  /**
   * Tests the Newsletter subscribe result.
   */
  public function testFromResponseData(): void {
    NewsletterSubscribeResult::fromResponseData([
      'isNew' => FALSE,
      'message' => 'hello',
    ]);
  }

}
