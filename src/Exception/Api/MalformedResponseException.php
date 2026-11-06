<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

/**
 * The response from Newsroom has an unexpected format.
 *
 * Examples:
 *   - A json header was expected, but is not present.
 *   - The response body contains invalid json.
 *   - The json structure is not what is expected for a given endpoint.
 */
class MalformedResponseException extends BadResponseException {

}
