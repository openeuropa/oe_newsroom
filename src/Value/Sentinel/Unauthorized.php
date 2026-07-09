<?php

namespace Drupal\oe_newsroom\Value\Sentinel;

/**
 * Indicates that a request is unauthorized.
 *
 * This is only used by endpoints where "unauthorized" is an expected result.
 * Other endpoints will throw an exception instead.
 */
enum Unauthorized {

  case Instance;

}
