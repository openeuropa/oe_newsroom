<?php

namespace Drupal\oe_newsroom_vcr\Helper;

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains methods to remove and restore default values for an array.
 */
class ArrayDefaultValuesHelper {

  /**
   * Removes default values from an array, recursively.
   *
   * @param array $values
   *   Original values.
   * @param array $defaults
   *   Defaults to remove, if keys and values are identical.
   * @param mixed|null $missing_sentinel
   *   Sentinel value for keys that are present in the defaults but missing in
   *   the original array.
   *   If this is NULL, an exception will be thrown for missing keys.
   * @param list<string> $trail
   *   The trail of keys in a recursive call.
   *   Used in exception messages.
   *
   * @return array
   *   The reduced array.
   *
   * @throws \Exception
   *   A key is present in the defaults, but missing in the original value, and
   *   the sentinel value was NULL.
   */
  public static function arrayRemoveDefaultsRecursive(array $values, array $defaults, mixed $missing_sentinel = NULL, array $trail = []): array {
    foreach ($defaults as $key => $default) {
      if (!array_key_exists($key, $values)) {
        if ($missing_sentinel === NULL) {
          throw new \Exception(sprintf(
            "Missing value for key '%s'.",
            implode('.', [...$trail, $key]),
          ));
        }
        $values[$key] = $missing_sentinel;
        continue;
      }
      $value = $values[$key];
      if (is_array($default) && !array_is_list($default) && is_array($value)) {
        $values[$key] = ArrayDefaultValuesHelper::arrayRemoveDefaultsRecursive(
          $value,
          $default,
          $missing_sentinel,
          [...$trail, $key],
        );
        if ($values[$key] === []) {
          unset($values[$key]);
        }
      }
      elseif ($value === $default) {
        unset($values[$key]);
      }
    }
    return $values;
  }

  /**
   * Restores default values into an array.
   *
   * @param array $values
   *   The array without the defaults.
   * @param array $defaults
   *   The default values.
   * @param mixed|null $missing_sentinel
   *   A sentinel value indicating keys that were missing in the original array.
   *
   * @return array
   *   The restored array.
   */
  public static function arrayRestoreDefaultsRecursive(array $values, array $defaults, mixed $missing_sentinel = NULL): array {
    $is_missing_sentinel = match (TRUE) {
      $missing_sentinel === NULL => fn () => FALSE,
      $missing_sentinel instanceof TaggedValue => fn ($value): bool => $value == $missing_sentinel,
      default => fn ($value): bool => $value === $missing_sentinel,
    };
    $result = [];
    foreach ($defaults as $key => $default) {
      if (!array_key_exists($key, $values)) {
        $result[$key] = $default;
        continue;
      }
      $value = $values[$key];
      if ($is_missing_sentinel($value)) {
        continue;
      }
      if (
        is_array($default) &&
        !array_is_list($default) &&
        is_array($value)
      ) {
        $result[$key] = ArrayDefaultValuesHelper::arrayRestoreDefaultsRecursive($value, $default, $missing_sentinel);
      }
      else {
        $result[$key] = $value;
      }
    }
    return $result;
  }

}
