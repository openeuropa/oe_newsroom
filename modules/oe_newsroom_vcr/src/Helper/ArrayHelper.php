<?php

namespace Drupal\oe_newsroom_vcr\Helper;

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains static methods to deal with arrays.
 */
class ArrayHelper {

  /**
   * Applies ksort() recursively.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return mixed
   *   The sorted value.
   */
  public static function ksortRecursive(mixed $value): mixed {
    if (is_array($value)) {
      $value = array_map(static::ksortRecursive(...), $value);
      if (!array_is_list($value)) {
        ksort($value);
      }
    }
    elseif ($value instanceof TaggedValue) {
      $value = new TaggedValue($value->getTag(), static::ksortRecursive($value->getValue()));
    }
    return $value;
  }

  /**
   * Transforms array values with a callback.
   *
   * Unlike native array_map(), this method also passes the array key to the
   * callback.
   *
   * @param callable(TValueIn, TKey, int): TValueOut $callback
   *   The callback to invoke on each array value.
   *   The first parameter is the array value.
   *   The second parameter is the array key.
   *   The third parameter is the index.
   * @param array<TKey, TValueIn> $array
   *   The original array.
   *
   * @template TKey of array-key
   * @template TValueIn
   * @template TValueOut
   *
   * @return array<TKey, TValueOut>
   *   The transformed array.
   *
   * @see array_map()
   */
  public static function arrayMapWithKeys(callable $callback, array $array): array {
    $result = [];
    foreach (array_keys($array) as $index => $key) {
      $result[$key] = $callback($array[$key], $key, $index);
    }
    return $result;
  }

  /**
   * Computes the difference between two arrays, comparing keys and values.
   *
   * Unlike array_diff_assoc(), this applies strict comparison and accepts
   * non-string values.
   *
   * @param array $values
   *   The original array.
   * @param array $defaults
   *   Array values to remove from the original array, if key and value match.
   *
   * @return array
   *   The reduced array.
   *
   * @see \array_diff_assoc()
   */
  public static function arrayDiffAssocStrict(array $values, array $defaults): array {
    return array_filter(
      $values,
      fn ($value, $key) => !array_key_exists($key, $defaults) || $defaults[$key] !== $value,
      ARRAY_FILTER_USE_BOTH,
    );
  }

}
