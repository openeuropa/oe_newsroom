<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The response from Newsroom has a non-200 response code.
 */
class FailureResponseException extends BadResponseException {

  /**
   * Constructor.
   *
   * @param string $message
   *   The exception message.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param string $data
   *   Parsed json data from the response body.
   * @param \Throwable|null $previous
   *   The previous exception, or NULL.
   */
  public function __construct(
    string $message,
    RequestInterface $request,
    ResponseInterface $response,
    protected readonly string $data,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct(
      $message,
      $request,
      $response,
      $previous,
    );
  }

  /**
   * Gets the message from the response body.
   *
   * @return string
   *   Message from response body.
   */
  public function getResponseMessage(): string {
    return $this->data;
  }

}
