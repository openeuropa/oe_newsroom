<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * A request was made, but the http client did not return a response.
 *
 * This points to misconfiguration or a bug.
 *
 * @see \GuzzleHttp\Exception\RequestException
 */
class ApiRequestException extends ApiException {

  /**
   * Constructor.
   *
   * @param string $message
   *   A custom exception message.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param \Psr\Http\Client\ClientExceptionInterface $previous
   *   The exception from the http client.
   */
  public function __construct(
    string $message,
    public readonly RequestInterface $request,
    ClientExceptionInterface $previous,
  ) {
    parent::__construct($message, previous: $previous);
  }

}
