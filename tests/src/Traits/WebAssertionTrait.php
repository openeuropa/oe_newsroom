<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

/**
 * Contains assert methods for web tests.
 */
trait WebAssertionTrait {

  /**
   * Asserts the page title.
   *
   * This is different from WebAssert::titleEquals() in two ways:
   * - It accounts for the site name part of the page title.
   *   (The expected site name is hard-coded, which means this cannot be simply
   *   copied to other projects)
   * - It outputs the actual title on failure.
   *
   * @param string $expected
   *   Expected title, without the site name part.
   *
   * @see \Drupal\Tests\WebAssert::titleEquals()
   */
  protected function assertPageTitle(string $expected): void {
    $site_name = 'Drupal';
    $title_element = $this->assertSession()->elementExists('css', 'title');
    $this->assertSame("$expected | $site_name", $title_element->getHtml());
  }

}
