<?php

namespace Drupal\Tests\oe_newsroom\Constraint;

/**
 * Constraint for an associative array which allows additional keys.
 */
final class AssocValuesContain extends AssocBase {

  /**
   * Constructs a new instance.
   *
   * @param array<\PHPUnit\Framework\Constraint\Constraint|array|scalar> $constraints
   *   A map of constraints or expected values per array key.
   *   Any array will be converted to a new SubsetAssoc.
   *   Any other value will be converted to IsIdentical.
   * @param bool $allowReorder
   *   TRUE to allow reordering, FALSE to require exact order.
   */
  public function __construct(
    array $constraints,
    bool $allowReorder = TRUE,
  ) {
    parent::__construct(
      $constraints,
      TRUE,
      $allowReorder,
    );
  }

}
