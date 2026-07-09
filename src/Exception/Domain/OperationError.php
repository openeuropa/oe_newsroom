<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Domain;

/**
 * A business operation failed due to a system failure.
 *
 * This points to a software bug or a misconfiguration.
 *
 *  How to handle this:
 *  - Log it as error, using the ExceptionLogger in this module, to be sure the
 *    full chain of previous exceptions appears in the log message.
 *  - Show a message telling the user that it did not work, and what their
 *    options are.
 */
class OperationError extends OperationFailure {

}
