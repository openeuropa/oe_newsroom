<?php

namespace Drupal\oe_newsroom_vcr\Vcr;

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Interface for VCR operations allowed at runtime.
 */
interface VcrRuntimeInterface {

  /**
   * Gets the current VCR mode.
   */
  public function getMode(): VcrMode;

  /**
   * Reads a record from the VCR, when in replay mode.
   *
   * @param int|null $position
   *   Position of the next record, to be updated by reference.
   *
   * @param-out int $position
   *
   * @return \Symfony\Component\Yaml\Tag\TaggedValue|null
   *   The record, or NULL if no record found.
   */
  public function readNextRecord(?int &$position): ?TaggedValue;

  /**
   * Writes a record to the VCR, when in recording mode.
   *
   * @param \Symfony\Component\Yaml\Tag\TaggedValue $record
   *   The record to add.
   */
  public function addRecord(TaggedValue $record): void;

  /**
   * Sets a failure message and marks the VCR as poisoned.
   *
   * @param string $failure
   *   The failure message to set.
   */
  public function setFailure(string $failure): void;

}
