<?php

namespace Drupal\oe_newsroom_vcr\Vcr;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * A storage for the VCR.
 */
#[NewsroomApiExplorer]
class VcrStore implements VcrRuntimeInterface {

  public function __construct(
    protected readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * Starts a replay.
   *
   * @param list<\Symfony\Component\Yaml\Tag\TaggedValue> $records
   *   The records to replay.
   */
  public function startReplay(array $records): void {
    $this->reset();
    $this->setRecords($records);
    $this->setMode(VcrMode::Replay);
  }

  /**
   * Asserts that no failure occurred during the current replay or recording.
   */
  public function assertNoFailure(): void {
    $failure = $this->getFailure();
    if ($failure !== NULL) {
      throw new \RuntimeException(sprintf(
        "A failure occured in the VCR:\n%s",
        $failure,
      ));
    }
  }

  /**
   * Ends the current replay.
   *
   * This is meant to be called from a test method.
   */
  public function endReplay(): void {
    if ($this->getMode() !== VcrMode::Replay) {
      throw new \RuntimeException(sprintf(
        "Expected VCR mode to be ::%s, found ::%s.",
        VcrMode::Replay->name,
        $this->getMode()->name,
      ));
    }
    $this->assertNoFailure();
    $record = $this->readNextRecord($position);
    if ($record !== NULL) {
      throw new \RuntimeException(
        sprintf(
          "Expected end of recording at position %s. Found:\n%s",
          $position,
          Yaml::encode($record),
        )
      );
    }
    $this->reset();
  }

  /**
   * Starts a recording session.
   */
  public function startRecording(): void {
    $this->reset();
    $this->setMode(VcrMode::Recording);
  }

  /**
   * Ends a recording session, and gets recorded values.
   *
   * @return list<\Symfony\Component\Yaml\Tag\TaggedValue>
   *   The recorded values.
   */
  public function endRecording(): array {
    if ($this->getMode() !== VcrMode::Recording) {
      throw new \RuntimeException(sprintf(
        "Expected VCR mode to be ::%s, found ::%s.",
        VcrMode::Recording->name,
        $this->getMode()->name,
      ));
    }
    $this->assertNoFailure();
    $records = $this->getRecords();
    $this->reset();
    return $records;
  }

  /**
   * Clears all stored values.
   */
  public function reset(): void {
    $this->keyValueStore()->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getMode(): VcrMode {
    $value = $this->keyValueStore()->get('mode', 'passthrough');
    return VcrMode::from($value);
  }

  /**
   * Sets the mode of the VCR.
   *
   * @param \Drupal\oe_newsroom_vcr\Vcr\VcrMode $mode
   *   The new mode.
   */
  protected function setMode(VcrMode $mode): void {
    if ($mode === VcrMode::Passthrough) {
      $this->keyValueStore()->delete('mode');
    }
    else {
      $this->keyValueStore()->set('mode', $mode->value);
    }
  }

  /**
   * Gets the last failure that occurred during the current replay or recording.
   */
  public function getFailure(): ?string {
    return $this->keyValueStore()->get('failure');
  }

  /**
   * {@inheritdoc}
   */
  public function setFailure(string $failure): void {
    $this->setMode(VcrMode::Poisoned);
    $this->keyValueStore()->set('failure', $failure);
  }

  /**
   * {@inheritdoc}
   */
  public function readNextRecord(?int &$position): ?TaggedValue {
    $position = $this->keyValueStore()->get('position', 0);
    $this->keyValueStore()->set('position', $position + 1);
    $record = $this->getRecords()[$position] ?? NULL;
    if ($record === NULL) {
      return NULL;
    }
    if (!$record instanceof TaggedValue) {
      throw new \RuntimeException("Expected a TaggedValue at position $position. Found:\n" . Yaml::encode($record));
    }
    return $record;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecord(TaggedValue $record): void {
    $records = $this->getRecords();
    $records[] = $record;
    $this->setRecords($records);
  }

  /**
   * Gets all records.
   *
   * This can be used in recording mode and in replay mode.
   *
   * @return list<\Symfony\Component\Yaml\Tag\TaggedValue>
   *   The currently stored records.
   */
  public function getRecords(): array {
    return $this->keyValueStore()->get('recording', []);
  }

  /**
   * Sets the records.
   *
   * This can be used in recording mode and in replay mode.
   *
   * @param list<\Symfony\Component\Yaml\Tag\TaggedValue> $records
   *   The records to be stored.
   */
  protected function setRecords(array $records): void {
    $this->keyValueStore()->set('recording', $records);
  }

  /**
   * Gets the key value store used as storage.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   A key value store.
   */
  protected function keyValueStore(): KeyValueStoreInterface {
    return $this->keyValueFactory->get('oe_newsroom_vcr.state');
  }

}
