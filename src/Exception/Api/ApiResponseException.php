<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Exception\Api;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The API had a response with a response code other than 200.
 */
class ApiResponseException extends ApiException {

  public function __construct(
    string $message,
    public readonly RequestInterface $request,
    public readonly ResponseInterface $response,
    int $code,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $code);
  }

  public function getResponseCode(): int {
    return $this->response->getStatusCode();
  }

  public function getResponseMessage(): string {
    return $this->response->getStatusCode();
  }

}
