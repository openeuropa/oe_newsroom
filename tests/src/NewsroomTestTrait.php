<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom;

use Drupal\oe_newsroom\Newsroom;

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

  /**
   * Sets default values for the oe_newsroom module configuration.
   *
   * @param array $values
   *   The values to use for the configuration. Default values are provided if
   *   missing.
   */
  protected function configureNewsroom(array $values = []): void {
    $values += [
      'universe' => 'example-universe',
      'app_id' => 'example-app',
    ];

    $config = \Drupal::configFactory()->getEditable(Newsroom::CONFIG_NAME);
    $config->setData($values + $config->get())->save();
  }

}
