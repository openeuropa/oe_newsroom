<?php

namespace Drupal\oe_newsroom_vcr\Vcr;

use Drupal\Core\State\StateInterface;

/**
 * A storage service for captured values.
 */
class CaptureStore {

  const STATE_KEY = 'oe_newsroom_vcr.capture';

  public function __construct(
    protected readonly StateInterface $state,
  ) {}

  /**
   * Clears the captured values storage.
   */
  public function reset(): void {
    $this->state->delete(self::STATE_KEY);
  }

  /**
   * Gets captured values.
   *
   * @return array<string, mixed>
   *   Captured values.
   */
  public function getCapturedValues(): array {
    return $this->state->get(self::STATE_KEY) ?? [];
  }

  /**
   * Records a captured value.
   *
   * @param string $name
   *   The name from the TaggedValue('Capture', $name).
   * @param mixed $value
   *   The actual value.
   */
  public function capture(string $name, mixed $value): void {
    $values = $this->getCapturedValues();
    if (!array_key_exists($name, $values)) {
      $values[$name] = $value;
      $this->state->set(self::STATE_KEY, $values);
    }
    elseif ($values[$name] !== $value) {
      throw new \LogicException(sprintf('The capture "%s" already exists, but with a different value.', $name));
    }
  }

}
