<?php

namespace Drupal\oe_newsroom_vcr\Vcr;

use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;

/**
 * Provides additional VCR operations exposed in the API explorer.
 */
#[NewsroomApiExplorer]
class VcrApiExplorerOperations {

  public function __construct(
    protected readonly VcrStore $vcr,
  ) {}

  /**
   * Stops the current replay or recording, and starts with the same records.
   */
  public function startReplayFromRecorded(): void {
    $records = $this->vcr->getRecords();
    $this->vcr->startReplay($records);
  }

  /**
   * Restarts the current replay.
   */
  public function restartReplay(): void {
    $records = $this->vcr->getRecords();
    $this->vcr->startReplay($records);
  }

  /**
   * Restarts the current recording, but returns results.
   *
   * @return list<mixed>|null
   *   The recorded records, or NULL if no recording was ongoing.
   */
  public function restartRecording(): ?array {
    if ($this->vcr->getMode() === VcrMode::Recording) {
      $ret = $this->vcr->endRecording();
    }
    else {
      $ret = NULL;
    }
    $this->vcr->startRecording();
    return $ret;
  }

}
