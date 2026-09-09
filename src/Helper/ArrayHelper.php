<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Helper;

/**
 * Contains static methods for array processing.
 */
class ArrayHelper {

  /**
   * Removes NULL and empty string from an array, while preserving keys.
   *
   * @param array<TKey, TValue|null|''> $values
   *   Values to filter.
   *
   * @template TKey of array-key
   * @template TValue of mixed
   *
   * @return array<TKey, TValue>
   *   The array without any '' or NULL.
   */
  public static function filter(array $values): array {
    return array_filter(
      $values,
      fn ($value) => $value !== '' && $value !== NULL,
    );
  }

  /**
   * Joins a list of (non-empty) ids by comma.
   *
   * @param array<non-empty-string|int> $ids
   *   An array of string or integer ids.
   *   Array keys are ignored.
   *
   * @return string
   *   The ids joined by comma.
   */
  public static function joinIdsByComma(array $ids): string {
    // Skip all validation checks if array is empty.
    if (!$ids) {
      return '';
    }
    // Empty values are not allowed and would cause confusion.
    foreach ($ids as $id) {
      if ($id === '') {
        throw new \InvalidArgumentException("Detected empty string in id list.");
      }
      if (!is_string($id) && !is_int($id)) {
        throw new \InvalidArgumentException(sprintf(
          "Expected non-empty strings and integers in id list, found %s.",
          get_debug_type($id),
        ));
      }
    }
    return implode(',', $ids);
  }

}
