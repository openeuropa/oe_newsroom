<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom;

use Drupal\oe_newsroom\Api\NewsroomMessengerInterface;

/**
 * Interface for services that instantiate newsroom messengers.
 */
interface NewsroomMessengerFactoryInterface {

  /**
   * Returns the newsroom messenger.
   *
   * @return \Drupal\oe_newsroom\Api\NewsroomMessengerInterface
   *   The newsletter messenger.
   */
  public function get(): NewsroomMessengerInterface;

}
