<?php

/**
 * @file
 * This file contains version-specific PHPStan ignores.
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$config = [];

if (version_compare(\Drupal::VERSION, '11.0.0', '<')) {
  // Drupal 10.6 uses PHPStan 1, which does not report these PHPStan 2 rules.
  // Do not fail that CI matrix entry when an ignore has no matching diagnostic.
  $config['parameters']['reportUnmatchedIgnoredErrors'] = FALSE;
  $config['parameters']['ignoreErrors'] = [
    // Hook attributes are introduced in Drupal 11.
    [
      'message' => '#^Attribute class Drupal\\\\Core\\\\Hook\\\\Attribute\\\\Hook does not exist\.$#',
      'path' => __DIR__ . '/modules/oe_newsroom_node/src/Hook/NewsroomSettingsFormAlter.php',
    ],
  ];
}

return $config;
