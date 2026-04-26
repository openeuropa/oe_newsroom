<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Domain;

use Drupal\oe_newsroom\Exception\Domain\OperationFailure;

/**
 * Indicates that a business operation was denied for given input values.
 *
 * How to handle this:
 * - Do not log it.
 * - Show a message telling the user that it did not work, and what their
 *   options are.
 *
 * @todo Distingiush different types of failure.
 */
class OperationDenied extends OperationFailure {

}
