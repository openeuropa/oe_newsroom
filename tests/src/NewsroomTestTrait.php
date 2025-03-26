<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom;

/**
 * Contains methods useful for Newsroom tests.
 */
trait NewsroomTestTrait {

  /**
   * Unset the API private key.
   */
  protected function unsetApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Set the API private key.
   */
  protected function setApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => 'phpunit-test-private-key',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

}
