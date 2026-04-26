<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The response has status code 400, indicating a bad request.
 *
 * This can mean that json was malformed.
 */
class BadRequestException extends FailureResponseException {

}
