<?php

namespace Drupal\oe_newsroom_vcr\Helper;

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains static methods that processes values marked for capturing.
 *
 * @internal
 */
class CapturingHelper {

  public const CAPTURE_TAG_NAME = 'Capture';

  /**
   * Processes two tree structures while capturing values.
   *
   * @param mixed $expected
   *   The expected tree structure.
   *   Some tree nodes may contain a TaggedValue('Capture', $name).
   *   Those nodes will be replaced with corresponding values from the actual
   *   tree structure, and the capture name and actual value are passed to the
   *   $collect callback.
   * @param mixed $actual
   *   The actual tree structure.
   * @param callable(string, mixed): void $collect
   *   A callback to collect captured values.
   *   The first parameter is the capture name, the second the actual value.
   *
   * @return mixed
   *   The expected array, but with all 'Capture' tagged values replaced with
   *   actual values.
   */
  public static function captureRecursive(mixed $expected, mixed $actual, callable $collect): mixed {
    if (is_array($expected)) {
      if (is_array($actual)) {
        foreach (array_intersect_key($expected, $actual) as $key => $expected_value) {
          $expected[$key] = static::captureRecursive($expected_value, $actual[$key], $collect);
        }
      }
    }
    elseif ($expected instanceof TaggedValue) {
      if ($expected->getTag() === self::CAPTURE_TAG_NAME) {
        assert(is_string($expected->getValue()));
        $collect($expected->getValue(), $actual);
        $expected = $actual;
      }
      elseif ($actual instanceof TaggedValue) {
        if ($expected->getTag() === $actual->getTag()) {
          $expected = new TaggedValue(
            $expected->getTag(),
            static::captureRecursive($expected->getValue(), $actual->getValue(), $collect),
          );
        }
      }
    }
    return $expected;
  }

}
