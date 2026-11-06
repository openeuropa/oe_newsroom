<?php

namespace Drupal\oe_newsroom_api_explorer\Helper;

/**
 * Contains static methods related to reflection.
 */
class ReflectionHelper {

  /**
   * Finds the first doc comment in the hierarchy without '(at)inheritdoc'.
   *
   * @param \ReflectionMethod $method
   *   The method for which to get the doc comment.
   *
   * @return string|null
   *   The doc comment, or NULL if none found.
   */
  public static function findOriginalMethodDocComment(\ReflectionMethod $method): ?string {
    foreach (static::exploreMethodHierarchy($method) as $parent_method) {
      $doc = $parent_method->getDocComment();
      if ($doc !== FALSE && !str_contains($doc, '@inheritdoc')) {
        return $doc;
      }
    }
    return NULL;
  }

  /**
   * Iterates a method and its parent and interface methods.
   *
   * @param \ReflectionMethod $method
   *   The original method.
   *
   * @return \Iterator<\ReflectionMethod>
   *   The method itself, and parent and interface implementations.
   */
  public static function exploreMethodHierarchy(\ReflectionMethod $method): \Iterator {
    yield $method->getDeclaringClass()->name => $method;
    $parent_class = $method->getDeclaringClass();
    while ($parent_class = $parent_class->getParentClass()) {
      if (!$parent_class->hasMethod($method->name)) {
        break;
      }
      $parent_method = $parent_class->getMethod($method->name);
      // Skip parent levels that do not provide an implementation.
      $parent_class = $parent_method->getDeclaringClass();
      yield $parent_class->name => $parent_method;
    }
    foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
      if (!$interface->hasMethod($method->name)) {
        continue;
      }
      $interface_method = $interface->getMethod($method->name);
      if ($interface_method->getDeclaringClass()->name !== $interface->name) {
        continue;
      }
      yield $interface->name => $interface_method;
    }
  }

  /**
   * Gets a ReflectionMethod object from a method closure.
   *
   * @param \Closure $method
   *   A closure of a method.
   *
   * @return \ReflectionMethod|null
   *   The reflection object, or NULL if the closure was not from a method.
   */
  public static function getReflectionMethodFromClosure(\Closure $method): ?\ReflectionMethod {
    $reflection_function = new \ReflectionFunction($method);
    return $reflection_function
      ->getClosureScopeClass()
      ?->getMethod($reflection_function->name);
  }

}
