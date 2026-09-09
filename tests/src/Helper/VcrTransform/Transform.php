<?php

namespace Drupal\Tests\oe_newsroom\Helper\VcrTransform;

use Drupal\oe_newsroom_vcr\Helper\ArrayHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains methods to create transformation functions.
 *
 * @internal
 *   This may be moved to a separate package at some point.
 */
class Transform {

  /**
   * Gets an identity function.
   *
   * @return \Closure(mixed): mixed
   *   An identity function.
   */
  public static function identity(): \Closure {
    return fn (mixed $value): mixed => $value;
  }

  /**
   * Gets a transformation that applies multiple transformations in sequence.
   *
   * @param list<callable(mixed): mixed> $transformations
   *   The transformations to apply, in order. Each one receives the result of
   *   the previous one.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function multiple(array $transformations): \Closure {
    return function ($value) use ($transformations): mixed {
      foreach ($transformations as $transformation) {
        $value = $transformation($value);
      }
      return $value;
    };
  }

  /**
   * Gets a transformation that transforms each record in an array.
   *
   * Each record is expected to be an associative array. The per-key
   * transformations are applied to the matching keys of every record.
   *
   * @param array<string, callable(mixed): mixed> $transformations
   *   Transformations keyed by the record key they apply to.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function eachAssocInArray(array $transformations): \Closure {
    return static::eachInArray(static::assoc($transformations));
  }

  /**
   * Gets a transformation that applies to each array value.
   *
   * @param callable(mixed): mixed $transformation
   *   The transformation to apply to each array value.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function eachInArray(callable $transformation): \Closure {
    return function (mixed $value) use ($transformation): mixed {
      if (!is_array($value)) {
        return $value;
      }
      return array_map($transformation, $value);
    };
  }

  /**
   * Gets a transformation that applies per-key transformations to an array.
   *
   * Only keys present in both the value and the given transformations are
   * transformed. Non-array values are returned unchanged.
   *
   * @param array<string, callable(mixed): mixed> $transformations
   *   Transformations keyed by the array key they apply to.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function assoc(array $transformations): \Closure {
    return function (mixed $assoc) use ($transformations): mixed {
      if (!is_array($assoc)) {
        return $assoc;
      }
      foreach (array_intersect_key($transformations, $assoc) as $key => $transformation) {
        $assoc[$key] = $transformation($assoc[$key]);
      }
      return $assoc;
    };
  }

  /**
   * Gets a transformation that applies to leaf values in a nested array.
   *
   * The array is traversed recursively, building a dot-separated key trail for
   * each leaf value. A transformation is applied to a leaf when its trail
   * matches one of the given keys.
   *
   * @param array<string, callable(mixed): mixed> $transformations
   *   Transformations keyed by the dot-separated path to the leaf value they
   *   apply to.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function nested(array $transformations): \Closure {
    $transform_recursive = function (mixed $tree, string $trail = '') use ($transformations, &$transform_recursive): mixed {
      if (is_array($tree)) {
        $terminated_trail = ($trail === '') ? '' : ($trail . '.');
        return ArrayHelper::arrayMapWithKeys(
          fn (mixed $subtree, string $key) => $transform_recursive(
            $subtree, $terminated_trail
            . $key
          ),
          $tree,
        );
      }
      $transformation = $transformations[$trail] ?? NULL;
      if ($transformation) {
        return $transformation($tree);
      }
      return $tree;
    };
    return function ($value) use ($transform_recursive): mixed {
      if (!is_array($value)) {
        return $value;
      }
      return $transform_recursive($value);
    };
  }

  /**
   * Gets a transformation that replaces one value with another.
   *
   * Integer and string representations are treated as equivalent: a stringified
   * integer matches an integer needle, and the replacement is cast to match the
   * type of the original value.
   *
   * @param mixed $old
   *   The value to replace.
   * @param mixed $new
   *   The replacement value.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function replace(mixed $old, mixed $new): \Closure {
    return function (mixed $value) use ($old, $new): mixed {
      if ($value === $old) {
        return $new;
      }
      if (is_string($value) && is_int($old) && is_int($new) && $value === (string) $old) {
        return (string) $new;
      }
      if (is_int($value) && is_string($old) && is_string($new) && (string) $value === $old) {
        return (int) $new;
      }
      return $value;
    };
  }

  /**
   * Gets a transformation that applies recursively to every value in a tree.
   *
   * The transformation is applied bottom-up: array items and tagged values are
   * transformed first, then the value that contains them. Tagged values are
   * preserved, with the transformation applied to the wrapped value.
   *
   * @param callable(mixed): mixed $transformation
   *   The transformation to apply to every value in the tree.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function deepRecursive(callable $transformation): \Closure {
    $function = function (mixed $value) use ($transformation, &$function): mixed {
      if (is_array($value)) {
        $value = array_map($function, $value);
      }
      elseif ($value instanceof TaggedValue) {
        $value = new TaggedValue(
          $value->getTag(),
          $function($value->getValue()),
        );
      }
      return $transformation($value);
    };
    return $function;
  }

  /**
   * Gets a transformation that applies recursively to every leaf in a tree.
   *
   * TaggedValue objects are treated as leaf nodes.
   *
   * @param callable(mixed, callable(mixed): mixed): mixed $transformation
   *   The transformation to apply to every value in the tree.
   *   The recursive function is passed as a second parameter, to allow for
   *   deeper recursion into TaggedValue instances.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function eachRecursive(callable $transformation): \Closure {
    $function = function (mixed $value) use ($transformation, &$function): mixed {
      if (is_array($value)) {
        $value = array_map($function, $value);
      }
      return $transformation($value, $function);
    };
    return $function;
  }

  /**
   * Gets a transformation that replaces date strings with unique values.
   *
   * Each distinct 'Y-m-d H:i:s' date string is mapped to a new date, starting
   * at the given offset and incrementing by a fixed number of seconds for each
   * subsequent distinct value.
   *
   * @param string $offset_date
   *   The first replacement date, as 'Y-m-d H:i:s' in UTC.
   * @param int $increment_seconds
   *   The number of seconds to add for each subsequent distinct value.
   * @param string|null $tag
   *   (optional) A tag name to wrap the replaced date.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   *
   * @throws \Exception
   *   If the offset date cannot be parsed into a date.
   */
  public static function uniqueDateString(string $offset_date, int $increment_seconds = 600, ?string $tag = NULL): \Closure {
    $pattern = '#^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$#';
    Assert::assertMatchesRegularExpression($pattern, $offset_date);
    $offset_timestamp = strtotime($offset_date . ' UTC');
    $fn_generate_nth_date_string = function (int $index) use ($offset_timestamp, $increment_seconds): string {
      $timestamp = $offset_timestamp + $index * $increment_seconds;
      $date = new \DateTimeImmutable("@$timestamp", new \DateTimeZone('UTC'));
      return $date->format('Y-m-d H:i:s');
    };
    $fn_replace = static::unique($fn_generate_nth_date_string);
    if ($tag !== NULL) {
      $fn_replace = static::tag($tag, $fn_replace);
    }
    return static::ifPattern($pattern, $fn_replace);
  }

  /**
   * Gets a transformation to replaces integers with stable ones.
   *
   * Each distinct integer value is mapped to a new integer, starting at the
   * given offset and adding the increment for each subsequent distinct value.
   *
   * @param int $offset
   *   The first replacement integer.
   * @param int $increment
   *   The amount to add for each subsequent distinct value.
   * @param string|null $tag
   *   (optional) Tag to wrap the replacement value.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function uniqueIntegerIncrement(int $offset, int $increment = 1, ?string $tag = NULL): \Closure {
    return static::ifIntegerLike(
      static::unique(fn (int $index): int => $offset + $index * $increment),
      $tag !== NULL ? static::tag($tag) : NULL,
    );
  }

  /**
   * Gets a transformation that replaces matching strings with unique values.
   *
   * Each distinct string matching the pattern is replaced with a value built
   * from the sprintf() template and an incrementing index.
   *
   * @param string $replace
   *   The regular expression a value must match to be replaced.
   * @param string $pattern
   *   A sprintf() template for the replacement. Must contain '%d', which is
   *   filled with an incrementing index per distinct value.
   * @param string|null $tag
   *   An optional tag to wrap the replacement value in, or NULL for none.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function uniquePatternSprintf(string $replace, string $pattern = '#.#', ?string $tag = NULL): \Closure {
    Assert::assertStringContainsString('%d', $replace);
    $create_value = fn (int $index) => sprintf($replace, $index);
    $transformation = static::unique($create_value);
    if ($tag !== NULL) {
      $transformation = static::tag($tag, $transformation);
    }
    return static::ifPattern($pattern, $transformation);
  }

  /**
   * Gets a transformation to map each distinct value to a unique replacement.
   *
   * Replacements are drawn in order from the given source. The first time a
   * value is seen it takes the next replacement; the same value seen again
   * returns the same replacement. Values are compared strictly. The returned
   * transformation throws a \RuntimeException if the replacements run out.
   *
   * @param iterable<T>|(callable(int): T) $replace_source
   *   An array, iterator or callback providing replacement values per index.
   *
   * @template T
   *
   * @return \Closure(mixed): T
   *   The resulting transformation.
   */
  public static function unique(iterable|callable $replace_source): \Closure {
    $replacements_iterator = match (TRUE) {
      is_array($replace_source) => new \ArrayIterator($replace_source),
      $replace_source instanceof \Iterator => $replace_source,
      $replace_source instanceof \IteratorAggregate => $replace_source->getIterator(),
      $replace_source instanceof \Traversable => new \IteratorIterator($replace_source),
      is_callable($replace_source) => (function () use ($replace_source): \Iterator {
        for ($i = 0;; ++$i) {
          yield $replace_source($i);
        }
      })(),
    };
    $replacements_iterator->rewind();

    $values = [];
    $replacements = [];
    return function ($value) use ($replacements_iterator, &$replacements, &$values): mixed {
      $known_index = array_search($value, $values, TRUE);
      if ($known_index !== FALSE) {
        return $replacements[$known_index];
      }
      if (!$replacements_iterator->valid()) {
        $index = count($values);
        throw new \RuntimeException("No replacements for index $index.");
      }
      $replacement = $replacements_iterator->current();
      $replacements_iterator->next();
      $replacements[] = $replacement;
      $values[] = $value;
      return $replacement;
    };
  }

  /**
   * Creates a transformation that wraps a value in a TaggedValue object.
   *
   * @param string $tag
   *   The new tag name to add.
   * @param callable|null $decorated
   *   The transformation to apply to the value, or NULL to leave as-is.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function tag(string $tag, ?callable $decorated = NULL): \Closure {
    return fn (mixed $value): TaggedValue => new TaggedValue(
      $tag,
      $decorated ? $decorated($value) : $value,
    );
  }

  /**
   * Applies a transformation if the value matches a regex pattern.
   *
   * @param string $pattern
   *   The regular expression pattern.
   * @param callable(mixed): mixed $then
   *   The transformation to apply if the pattern matches.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ifPattern(string $pattern, callable $then): \Closure {
    return function (mixed $value) use ($pattern, $then): mixed {
      if (is_string($value) && preg_match($pattern, $value)) {
        return $then($value);
      }
      return $value;
    };
  }

  /**
   * Applies a callback if the value is a string.
   *
   * @param callable(string): mixed $then
   *   Transformation if the value is a string.
   * @param (callable(string): mixed)|null $else
   *   (optional) Transformation if the value is not a string.
   *
   * @return \Closure(mixed): mixed
   *   Resulting transformation.
   */
  public static function ifString(callable $then, ?callable $else = NULL): \Closure {
    return static::if(is_string(...), $then, $else);
  }

  /**
   * Applies a transformation if the value is an integer or integer-like string.
   *
   * If the value is a stringified integer, it is cast to integer before being
   * passed to the callback, and the result is cast back to string. The callback
   * is asserted to return an integer.
   *
   * @param callable(int): int $decorated
   *   The transformation to apply to the integer value.
   * @param (callable(mixed): mixed)|null $wrapper
   *   (optional) Transformation applied to the re-typed replacement value.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ifIntegerLike(callable $decorated, ?callable $wrapper = NULL): \Closure {
    return function (mixed $value) use ($decorated, $wrapper): mixed {
      if (is_int($value)) {
        $replacement = $decorated($value);
        Assert::assertIsInt($replacement);
      }
      elseif (is_string($value) && (string) (int) $value === $value) {
        $replacement = $decorated((int) $value);
        Assert::assertIsInt($replacement);
        $replacement = (string) $replacement;
      }
      else {
        return $value;
      }
      return $wrapper !== NULL ? $wrapper($replacement) : $replacement;
    };
  }

  /**
   * Applies a transformation conditionally.
   *
   * @param callable(mixed): bool $condition
   *   The condition.
   * @param callable(mixed): mixed $then
   *   The transformation if the condition is TRUE.
   * @param callable|null $else
   *   The transformation if the condition is FALSE.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function if(callable $condition, callable $then, ?callable $else = NULL): \Closure {
    return function (mixed $value) use ($condition, $then, $else): mixed {
      if ($condition($value)) {
        return $then($value);
      }
      if ($else !== NULL) {
        return $else($value);
      }
      return $value;
    };
  }

  /**
   * Gets a transformation to resolve tagged values through the hierarchy.
   *
   * @param array<string, callable(mixed): mixed> $transformations_by_tag
   *   Transformations by tag name.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function resolveTagsRecursive(array $transformations_by_tag): \Closure {
    return static::deepRecursive(static::resolveTags($transformations_by_tag));
  }

  /**
   * Gets a transformation to resolve specific tagged values.
   *
   * @param array<string, callable(mixed): mixed> $transformations_by_tag
   *   Transformations by tag name.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function resolveTags(array $transformations_by_tag): \Closure {
    return function (mixed $value) use ($transformations_by_tag): mixed {
      if (!$value instanceof TaggedValue) {
        return $value;
      }
      $transformation = $transformations_by_tag[$value->getTag()] ?? NULL;
      if ($transformation === NULL) {
        return $value;
      }
      return $transformation($value->getValue());
    };
  }

  /**
   * Applies a transformation if a tag is present, preserving the tag.
   *
   * @param string $tag
   *   Tag name to look for.
   * @param callable(mixed): mixed $decorated
   *   The transformation to apply to the value if the tag is present.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ifTag(string $tag, callable $decorated): \Closure {
    return static::ifTagRetag($tag, $tag, $decorated);
  }

  /**
   * Applies a transformation if a tag is present, replacing the tag.
   *
   * @param string $tag
   *   Tag name to look for.
   * @param string $new_tag
   *   Replacement tag name.
   * @param (callable(mixed): mixed)|null $decorated
   *   The transformation to apply to the value if the tag is present.
   *   Pass NULL to leave the value unchanged.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ifTagRetag(string $tag, string $new_tag, ?callable $decorated = NULL): \Closure {
    return static::ifTagUntag($tag, static::tag($new_tag, $decorated));
  }

  /**
   * Applies a transformation if a tag is present, removing the tag.
   *
   * @param string $tag
   *   Tag name to look for.
   * @param (callable(mixed): mixed)|null $decorated
   *   The transformation to apply to the value if the tag is present.
   *   Pass NULL to leave the value unchanged.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ifTagUntag(string $tag, ?callable $decorated = NULL): \Closure {
    return function (mixed $value) use ($tag, $decorated): mixed {
      if ($value instanceof TaggedValue && $value->getTag() === $tag) {
        return ($decorated !== NULL) ? $decorated($value->getValue()) : $value->getValue();
      }
      return $value;
    };
  }

  /**
   * Gets a transformation to replace any value with the same default value.
   *
   * @param mixed $replacement
   *   The replacement value.
   * @param string|null $tag
   *   (optional) A tag name to wrap the replacement.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  public static function ignore(mixed $replacement, ?string $tag = NULL): \Closure {
    if ($tag !== NULL) {
      $replacement = new TaggedValue($tag, $replacement);
    }
    return fn () => $replacement;
  }

}
