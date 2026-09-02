<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

/**
 * The response has status code 400, indicating a bad request.
 *
 * This can mean that json was malformed.
 */
class BadRequestException extends FailureResponseException {

}
