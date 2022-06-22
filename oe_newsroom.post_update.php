<?php

/**
 * @file
 * OE Newsroom post updates.
 */

declare(strict_types = 1);

/**
 * Change the name to 'normalised'.
 */
function oe_newsroom_post_update_00001(&$sandbox) {
  $config = \Drupal::configFactory()->getEditable('oe_newsroom.settings');
  if (($normalised = $config->get('normalized')) !== NULL) {
    $config->set('normalised', $normalised);
  }
  $config->clear('normalized');
  $config->save();
}
