<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Exception;

/**
 * Exception thrown when the Newsroom API does not return a valid response.
 */
class InvalidResponseException extends ClientException {}
