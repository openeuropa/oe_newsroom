<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use PHPUnit\Framework\AssertionFailedError;

/**
 * Contains methods to catch and report exceptions.
 */
trait TryAndCatchTrait {

  /**
   * An additional exception to report at the end of the test.
   */
  protected ?\Throwable $originalException = NULL;

  /**
   * Catches and returns an exception thrown from a callback.
   *
   * If no exception is thrown in the callback, the method will fail.
   *
   * If the caught exception does not match expectations, a failure is thrown,
   * with the caught exception being printed in the test output.
   *
   * @param callable(): void $callback
   *   The callback.
   * @param class-string<T>|null $class
   *   The expected exception class, or NULL to allow any.
   *   The assertion is for identity, not instanceof.
   * @param string|null $message
   *   The expected exception message, or NULL to allow any.
   * @param (callable(\Throwable&T): void)|null $validate
   *   A validation callback for the caught exception.
   *   On failure, the original exception will be printed in the test output.
   *
   * @return \Throwable&T
   *   The exception that was caught.
   *
   * @template T of \Throwable
   */
  protected function tryAndCatch(callable $callback, ?string $class = NULL, ?string $message = NULL, ?callable $validate = NULL): \Throwable {
    try {
      $callback();
    }
    catch (\Throwable $exception) {
      $this->originalException = $exception;
      if ($class !== NULL) {
        // First assert instanceof, to distinguish whether the caught exception
        // is a subclass or something completely different.
        $this->assertInstanceOf($class, $exception);
        $this->assertSame($class, get_class($exception));
      }
      if ($message !== NULL) {
        $this->assertSame($message, $exception->getMessage());
      }
      if ($validate !== NULL) {
        $validate($exception);
      }
      $this->originalException = NULL;
      return $exception;
    }
    $this->fail('Expected exception not thrown.');
  }

  /**
   * Runs after the test, and throws the additional exception if it exists.
   *
   * This is meant to provide useful context in the test output.
   *
   * @after
   */
  public function afterTestThrowOriginalException(): void {
    if ($this->originalException !== NULL) {
      // Wrap the original exception into AssertionFailedError, to make sure
      // that phpunit will print it.
      throw new AssertionFailedError('Original exception', previous: $this->originalException);
    }
  }

}
