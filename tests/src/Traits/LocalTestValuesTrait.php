<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\Core\Site\Settings;
use Drupal\oe_newsroom\Newsroom;
use Drupal\Tests\oe_newsroom\Helper\VcrTransform\NewsroomVcrTransform;
use Drupal\Tests\oe_newsroom\Value\NewsroomTestValues;

/**
 * Contains a method to load per-environment test values in "recording" mode.
 */
trait LocalTestValuesTrait {

  /**
   * Contains values to use in the test.
   */
  protected NewsroomTestValues $newsroomTestValues;

  /**
   * Configures the Newsroom client, and sets transformations for the VCR.
   */
  protected function initializeNewsroomAndVcrWithTestValues(): void {
    $test_values = $this->loadNewsroomTestValuesObject($this->isRecording());
    if ($this->isRecording()) {
      $this->vcrPack = NewsroomVcrTransform::fnPackRecords(
        $test_values,
        $this->loadNewsroomTestValuesObject(FALSE),
      );
    }
    else {
      $this->vcrUnpack = NewsroomVcrTransform::fnUnpackRecords();
    }

    $settings = Settings::getAll();
    $settings['oe_newsroom']['newsroom_api_key'] = $test_values->privateKey;
    new Settings($settings);

    $config = \Drupal::configFactory()->getEditable(Newsroom::CONFIG_NAME);
    $config->setData($test_values->getNewsroomModuleSettings());
    $config->save();

    $this->newsroomTestValues = $test_values;
  }

  /**
   * Loads a test values object.
   *
   * @param bool $use_local_values
   *   TRUE to load local values from 'test-values.php'.
   *   FALSE to load dist values from 'test-values.example.php'.
   *
   * @return \Drupal\Tests\oe_newsroom\Value\NewsroomTestValues
   *   A value object with test values.
   */
  protected function loadNewsroomTestValuesObject(bool $use_local_values): NewsroomTestValues {
    if ($use_local_values) {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.php';
      $missing_file_message = 'Please copy `test-values.example.php` to `test-values.php`, and replace the values to connect to a real Newsroom sandbox.';
    }
    else {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.example.php';
      $missing_file_message = '';
    }
    $this->assertFileExists($test_values_file, $missing_file_message);
    $this->assertFileIsReadable($test_values_file);
    $test_values_object = include $test_values_file;
    $this->assertInstanceOf(NewsroomTestValues::class, $test_values_object);
    return $test_values_object;
  }

}
