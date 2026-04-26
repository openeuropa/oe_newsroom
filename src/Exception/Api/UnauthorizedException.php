<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The response from Newsroom has a non-200 response code.
 */
class UnauthorizedException extends FailureResponseException {

}
