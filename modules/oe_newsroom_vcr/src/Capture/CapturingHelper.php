<?php

namespace Drupal\oe_newsroom_vcr\Capture;

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains static methods that processes values marked for capturing.
 *
 * The capture mechanism allows VCR data to contain placeholder values, marked
 * with the `!Capture` yaml tag.
 *
 * In replay mode, when asserting that an actual value matches a pre-recorded
 * value, all these placeholders in the VCR data are first replaced with the
 * corresponding actual value at the same tree position, before the comparison
 * is done. The actual values are collected, together with the placeholder key.
 *
 * Later, the test can do assertions about the collected values.
 *
 * The main existing use case is the authentication token in an API request,
 * which depends on dynamic values and should not be part of the VCR yaml file
 * in the fixtures directory.
 *
 * @see \Drupal\oe_newsroom_vcr\Capture\CaptureStore
 *
 * @internal
 */
class CapturingHelper {

  public const CAPTURE_TAG_NAME = 'Capture';

  /**
   * Processes two tree structures while capturing values.
   *
   * This will be called in a replay run, before asserting that actual data
   * matches pre-recorded VCR data, if that VCR data contains placeholders
   * marked with the `!Capture` yaml tag.
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
        if (!is_string($expected->getValue())) {
          throw new \RuntimeException(sprintf(
            'Expected a string as value for a `!Capture` tag, found %s.',
            get_debug_type($expected->getValue()),
          ));
        }
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
