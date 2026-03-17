<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Unit;

use Drupal\oe_newsroom\Value\NewsletterSubscribeResult;
use Drupal\Tests\UnitTestCase;

class NewsletterSubscribeResultTest extends UnitTestCase {

  public function testFromResponseData(): void {
    NewsletterSubscribeResult::fromResponseData([
      'isNew' => false,
      'message' => 'hello',
    ]);
  }

}
