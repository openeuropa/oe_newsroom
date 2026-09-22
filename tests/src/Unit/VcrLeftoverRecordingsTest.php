<?php

namespace Drupal\Tests\oe_newsroom\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * A simple test case to detect leftover recordings from renamed tests.
 */
class VcrLeftoverRecordingsTest extends UnitTestCase {

  /**
   * Tests that no leftover recordings exist.
   */
  public function testNoLeftoverRecordings(): void {
    $candidates = scandir(dirname(__DIR__, 2) . '/fixtures/vcr');
    $leftovers = [];
    foreach ($candidates as $candidate) {
      if (preg_match('#^(Drupal\.Tests(?:\.\w+)+Test)\.(test\w+)\.yml#', $candidate, $matches)) {
        $class = str_replace('.', '\\', $matches[1]);
        $method = $matches[2];
        if (!class_exists($class)) {
          $leftovers['class'][] = $candidate;
        }
        elseif (!method_exists($class, $method)) {
          $leftovers['method'][] = $candidate;
        }
      }
    }
    // Do not clutter the output with a comparison assertion.
    if ($leftovers) {
      $this->fail("Leftover recordings found:\n" . Yaml::encode($leftovers) . "\n");
    }
    $this->addToAssertionCount(1);
  }

}
