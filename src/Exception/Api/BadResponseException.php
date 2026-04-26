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

  public function getRequest(): RequestInterface {
    return $this->request;
  }

  public function getResponse(): ResponseInterface {
    return $this->response;
  }

  public function getResponseCode(): int {
    return $this->response->getStatusCode();
  }

}
