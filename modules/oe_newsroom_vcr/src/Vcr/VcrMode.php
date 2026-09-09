<?php

namespace Drupal\oe_newsroom_vcr\Vcr;

/**
 * Represents different modes that the VCR can be in.
 *
 * @internal
 */
enum VcrMode: string {

  /*
   * The VCR is replaying records from a previous run.
   */
  case Replay = 'replay';

  /*
   * The VCR is recording from scratch.
   */
  case Recording = 'recording';

  /*
   * The VCR encountered at least one failure in the current run.
   */
  case Poisoned = 'poisoned';

  /*
   * The VCR is in passthrough mode. Nothing should be read or written.
   */
  case Passthrough = 'passthrough';

}
