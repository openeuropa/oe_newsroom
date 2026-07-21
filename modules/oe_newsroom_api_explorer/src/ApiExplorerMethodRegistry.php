<?php

namespace Drupal\oe_newsroom_api_explorer;

use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorerExclude;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Provides services that are explorable with the API explorer.
 */
class ApiExplorerMethodRegistry {

  const TAG_NAME = 'oe_newsroom_api_explorer';

  public function __construct(
    #[AutowireLocator(self::TAG_NAME)]
    private readonly ServiceProviderInterface $services,
  ) {}

  /**
   * Gets a service method as a closure.
   *
   * @param string $id
   *   The method identifier, as found in the select options.
   *
   * @return \Closure|null
   *   The method callable, or NULL if not found.
   */
  public function getMethodAsClosure(string $id): ?\Closure {
    $parts = explode('::', $id);
    if (count($parts) !== 2) {
      throw new \InvalidArgumentException("Invalid id passed: '$id'.");
    }
    [$service_id, $method_name] = $parts;
    $service = $this->getServiceIfExplorable($service_id);
    if ($service === NULL) {
      return NULL;
    }
    $methods = $this->getExplorableServiceMethods($service);
    $method = $methods[$method_name] ?? NULL;
    if ($method === NULL) {
      return NULL;
    }
    assert(method_exists($service, $method_name));
    return $service->$method_name(...);
  }

  /**
   * Gets all explorable methods, as select options.
   *
   * @return array<string, array<string, \Drupal\Component\Render\MarkupInterface|string>>
   *   Select options to choose the method to explore.
   */
  public function getSelectOptions(): array {
    $options = [];
    $service_ids = array_keys($this->services->getProvidedServices());
    sort($service_ids);
    foreach ($service_ids as $service_id) {
      $service = $this->getServiceIfExplorable($service_id);
      if ($service === NULL) {
        continue;
      }
      foreach ($this->getExplorableServiceMethods($service) as $method) {
        $options[$service_id][$service_id . '::' . $method->name] = $method->getDeclaringClass()->getShortName() . '::' . $method->name;
      }
    }
    return $options;
  }

  /**
   * Gets a service instance, if it is explorable.
   *
   * @param string $service_id
   *   The service id.
   *
   * @return object|null
   *   The service.
   */
  protected function getServiceIfExplorable(string $service_id): ?object {
    $service = $this->services->get($service_id);
    if ($service === NULL) {
      return NULL;
    }
    $class = get_class($service);
    if ($class !== $service_id) {
      return NULL;
    }
    $reflection = new \ReflectionClass($class);
    $reflection->getAttributes(NewsroomApiExplorer::class);
    if ($reflection->getAttributes(NewsroomApiExplorer::class) === []) {
      return NULL;
    }
    return $service;
  }

  /**
   * Gets explorable methods for a service instance.
   *
   * @param object $service
   *   The service instance.
   *
   * @return array<string, \ReflectionMethod>
   *   Explorable methods by method name.
   */
  protected function getExplorableServiceMethods(object $service): array {
    $reflection = new \ReflectionObject($service);
    $public_methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
    $filtered_methods = [];
    foreach ($public_methods as $method) {
      if ($method->isStatic() || $method->isConstructor()) {
        continue;
      }
      if ($method->getFileName() !== $reflection->getFileName()) {
        // The method is defined in a parent class or trait.
        continue;
      }
      if ($method->getAttributes(NewsroomApiExplorerExclude::class) !== []) {
        continue;
      }
      $filtered_methods[$method->name] = $method;
    }
    return $filtered_methods;
  }

}
