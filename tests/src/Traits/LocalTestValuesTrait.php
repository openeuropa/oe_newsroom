<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\Component\Serialization\Yaml;

/**
 * Contains a method to load per-environment test values in "recording" mode.
 */
trait LocalTestValuesTrait {

  /**
   * Loads test values.
   *
   * @param bool $use_local_values
   *   TRUE to load local values from 'test-values.yml'.
   *   FALSE to load dist values from 'test-values.yml.dist'.
   *
   * phpcs:disable Drupal.Commenting.FunctionComment.ReturnCommentIndentation
   * @return array{
   *   oe_newsroom_settings: array,
   *   newsroom_api_private_key: string,
   *   node_notification_section_id: int,
   * }
   *   Values to use in the test.
   */
  protected function loadNewsroomTestValues(bool $use_local_values): array {
    if ($use_local_values) {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.yml';
      $missing_file_message = 'Please copy `test-values.yml.dist` to `test-values.yml`, and replace the values to connect to a real Newsroom sandbox.';
    }
    else {
      $test_values_file = dirname(__DIR__, 3) . '/test-values.yml.dist';
      $missing_file_message = '';
    }
    $this->assertFileExists($test_values_file, $missing_file_message);
    $this->assertFileIsReadable($test_values_file);
    $test_values_yaml = file_get_contents($test_values_file);
    $test_values = Yaml::decode($test_values_yaml);
    $this->assertIsArray($test_values['oe_newsroom_settings']);
    $this->assertIsString($test_values['oe_newsroom_settings']['node_service_id']);
    if (empty($test_values['newsroom_api_private_key'])) {
      if (!empty($test_values['newsroom_api_private_key_env_name'])) {
        $test_values['newsroom_api_private_key'] = getenv($test_values['newsroom_api_private_key_env_name']);
      }
      else {
        $test_values['newsroom_api_private_key'] = getenv('NEWSROOM_API_PRIVATE_KEY');
      }
    }
    $this->assertNotEmpty($test_values['newsroom_api_private_key']);
    return $test_values;
  }

}
