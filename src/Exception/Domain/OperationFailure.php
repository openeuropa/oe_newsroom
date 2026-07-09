<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Domain;

/**
 * A business operation failed to complete.
 *
 * The cause could be a technical problem, or a constraint that makes the
 * operation not possible with given input values.
 *
 * This is mostly used as a base class.
 */
class OperationFailure extends \Exception {

}
