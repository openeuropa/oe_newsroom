<?php

namespace Drupal\oe_newsroom\Helper;

/**
 * Contains methods to cast values.
 */
class ValueHelper {

  /**
   * Converts a value to integer ID (greater than zero) or NULL.
   *
   * @param numeric-string|int|null $value
   *   The value to convert.
   *   An empty string will become NULL.
   *   Otherwise, if a string is not integer-shaped, or if the integer value is
   *   not greater than zero, an exception is thrown.
   *
   * @return positive-int|null
   *   The converted value.
   *
   * @throws \InvalidArgumentException
   *   The value is a non-integer-shaped string.
   */
  public static function toIntIdOrNull(string|int|null $value): ?int {
    $result = self::toIntOrNull($value);
    if ($result === NULL || $result > 0) {
      return $result;
    }
    throw new \InvalidArgumentException(
      sprintf(
        "Expected a number greater than zero, found %s.",
        var_export($value, TRUE),
      )
    );
  }

  /**
   * Converts a value to integer or NULL.
   *
   * @param numeric-string|int|null $value
   *   The value to convert.
   *   An empty string will become NULL.
   *   Otherwise, if a string is not integer-shaped, an exception is thrown.
   *
   * @return int|null
   *   The converted value.
   *
   * @throws \InvalidArgumentException
   *   The value is a non-integer-shaped string.
   */
  public static function toIntOrNull(string|int|null $value): ?int {
    return match (TRUE) {
      $value === '', $value === NULL => NULL,
      is_int($value) => $value,
      $value === (string) (int) $value => (int) $value,
      default => throw new \InvalidArgumentException(sprintf(
        "Expected an integer-shaped string, found '%s'.",
        $value,
      )),
    };
  }

}
