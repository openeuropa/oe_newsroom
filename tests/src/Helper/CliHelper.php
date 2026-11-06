<?php

namespace Drupal\Tests\oe_newsroom\Helper;

use PHPUnit\Framework\Assert;

/**
 * Contains methods for cli interactions during a test run.
 *
 * This is mostly relevant during "recording" mode.
 */
class CliHelper {

  /**
   * Pauses to collects cli input.
   *
   * This should only be used in 'recording' mode.
   *
   * @param string $message
   *   The message to show.
   *
   * @return string
   *   The cli input.
   */
  public static function pauseOnCliPrompt(string $message): string {
    $tty = fopen('/dev/tty', 'rw+');
    fwrite($tty, $message);
    fflush($tty);
    $input = fgets($tty);
    fclose($tty);
    Assert::assertNotFalse($input);
    return $input;
  }

}
