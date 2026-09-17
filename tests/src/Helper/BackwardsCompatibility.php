<?php

namespace Drupal\Tests\oe_newsroom\Helper;

use PHPUnit\Framework\Assert;

/**
 * Contains some methods to support PhpUnit 9.
 */
class BackwardsCompatibility {

  /**
   * Asserts that a value is a list.
   *
   * @param mixed $actual
   *   The value to test.
   * @param string $message
   *   A message to show on failure.
   *
   * @phpstan-assert list<mixed> $array
   *
   * @see \PHPUnit\Framework\BackwardsCompatibility::assertIsList()
   */
  public static function assertIsList(mixed $actual, string $message = ''): void {
    if (method_exists(Assert::class, 'assertIsList')) {
      Assert::assertIsList($actual, $message);
    }
    else {
      // The method is not available in PhpUnit 9.
      Assert::assertIsArray($actual, $message);
      Assert::assertTrue(array_is_list($actual), $message);
    }
  }

}
