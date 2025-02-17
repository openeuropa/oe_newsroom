<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom;

use Drupal\oe_newsroom\Newsroom;

/**
 * Contains methods useful for Newsroom tests.
 */
trait NewsroomConfigurationTestTrait {

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
