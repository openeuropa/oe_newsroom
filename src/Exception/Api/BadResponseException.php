<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The response from Newsroom is malformed or indicates failure.
 */
class BadResponseException extends ApiException {

  public function __construct(
    string $message,
    protected readonly RequestInterface $request,
    protected readonly ResponseInterface $response,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, previous: $previous);
  }

  /**
   * Gets the request.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request.
   */
  public function getRequest(): RequestInterface {
    return $this->request;
  }

  /**
   * Gets the response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  public function getResponse(): ResponseInterface {
    return $this->response;
  }

  /**
   * Gets the response status code.
   *
   * @return int
   *   The HTTP status code of the response.
   */
  public function getResponseCode(): int {
    return $this->response->getStatusCode();
  }

}
