<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Constraint;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Base class for constraints on associative arrays.
 */
abstract class AssocBase extends Constraint {

  /**
   * Constructs a new instance.
   *
   * @param array<\PHPUnit\Framework\Constraint\Constraint|array|scalar|\Closure(mixed, string): void> $constraints
   *   A map of constraints or expected values per array key.
   *   Any array will be converted to a new SubsetAssoc.
   *   Any other value will be converted to IsIdentical.
   *   A closure will simply be called with the value and a message.
   * @param bool $allowAdditionalKeys
   *   TRUE to allow additional keys, FALSE to require the exact same keys.
   * @param bool $allowReorder
   *   TRUE to allow arbitrary order, FALSE to require the exact same order.
   */
  public function __construct(
    protected array $constraints,
    protected readonly bool $allowAdditionalKeys,
    protected readonly bool $allowReorder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function evaluate(mixed $other, string $description = '', bool $returnResult = FALSE): ?bool {
    if ($returnResult) {
      try {
        $this->doEvaluate($other, $description);
        return TRUE;
      }
      catch (AssertionFailedError) {
        return FALSE;
      }
    }
    else {
      $this->doEvaluate($other, $description);
      return NULL;
    }
  }

  /**
   * Evaluates the constraint, but without the `$returnResult` option.
   *
   * @param mixed $other
   *   The actual value.
   * @param string $description
   *   The description on failure.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   One of the assertions failed.
   */
  protected function doEvaluate(mixed $other, string $description): void {
    $message_prefix = $description === '' ? '' : $description . "\n";

    if (!is_array($other)) {
      Assert::fail(sprintf(
        '%sExpected an array, found %s',
        $message_prefix,
        get_debug_type($other),
      ));
    }

    $missing_keys = array_diff(array_keys($this->constraints), array_keys($other));
    if ($missing_keys) {
      Assert::fail(sprintf(
        '%sMissing keys: %s',
        $message_prefix,
        VarExporter::export($missing_keys),
      ));
    }

    if (!$this->allowAdditionalKeys) {
      $unexpected_keys = array_diff(array_keys($other), array_keys($this->constraints));
      if ($unexpected_keys) {
        Assert::fail(sprintf(
          '%sUnexpected keys: %s',
          $message_prefix,
          VarExporter::export($unexpected_keys),
        ));
      }
    }

    if (!$this->allowReorder) {
      Assert::assertSame(
        array_keys($this->constraints),
        array_intersect(array_keys($other), array_keys($this->constraints)),
        $message_prefix . 'Unexpected order of keys.',
      );
    }

    $expected_same = [];
    foreach ($this->constraints as $key => $constraint) {
      $child_message = $message_prefix . '[' . var_export($key, TRUE) . ']';
      if ($constraint instanceof Constraint) {
        $constraint->evaluate($other[$key], $child_message);
      }
      elseif (is_array($constraint)) {
        (new AssocValuesContain($constraint))->evaluate($other[$key]);
      }
      elseif ($constraint instanceof \Closure) {
        $constraint($other[$key], $child_message);
      }
      elseif (!is_object($constraint)) {
        // Collect expectations of identity, to assert them all at once later.
        $expected_same[$key] = $constraint;
      }
      else {
        // This is a programming error in the test itself, not a failure in the
        // system under test.
        throw new \InvalidArgumentException(sprintf(
          "%s\nExpected Constraint|\Closure|scalar|array at key %s, found %s",
          $child_message,
          var_export($key, TRUE),
          get_debug_type($constraint),
        ));
      }
    }
    // Assert all expected identities in a single assertion, to provide a more
    // complete failure output.
    if ($expected_same !== []) {
      Assert::assertSame(
        $expected_same,
        array_replace(
          $expected_same,
          array_intersect_key($other, $expected_same),
        ),
        $message_prefix . 'Array values must match.',
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    // This method is not really needed.
    throw new \RuntimeException('Not implemented.');
  }

}
