<?php

/**
 * @file
 * This file provides test values for a phpunit test.
 *
 * The values from `test-values.example.php` are used in regular test mode.
 *
 * To be able to update VCR recordings in tests/fixtures/, the developer should
 * make a local copy of `test-values.example.php`, named `test-values.php`, and
 * replace the values to connect to a real Newsroom (sandbox) instance and
 * universe.
 *
 * The values from `test-values.php` are used in "recording" mode, when the
 * UPDATE_TESTS environment variable is set.
 */

declare(strict_types=1);

use Drupal\Tests\oe_newsroom\Value\NewsroomTestValues;

return new NewsroomTestValues(
  // This value goes to site settings.
  // You might want to call `getenv('NEWSROOM_API_PRIVATE_KEY')`.
  privateKey: 'phpunit-test-private-key',
  // These values go to module settings.
  hashMethod: 'md5',
  normalised: TRUE,
  universe: 'test-universe',
  appId: 'test-app-id',
  nodeNotificationServiceId: 1234,
  // These values are used in the test itself.
  nodeNotificationSectionId: 77744,
  nodeNotificationTopicName: 'Node notification topic',
  subscriberEmail: 'teSt@eXample.com',
);
