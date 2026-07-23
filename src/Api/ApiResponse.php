<?php

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Yaml;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Api\FailureResponseException;
use Drupal\oe_newsroom\Exception\Api\MalformedResponseException;
use Drupal\oe_newsroom\Exception\Api\NotFoundException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Return value that provides access to request and response.
 */
class ApiResponse {

  /**
   * Response body. This is lazily populated.
   */
  private string|null $body = NULL;

  /**
   * Parsed json data. This is lazily populated.
   *
   * When this is not NULL, it means that the response was already validated,
   * including the json header.
   */
  private array|string|null $data = NULL;

  /**
   * Constructs a new instance.
   *
   * @param string $endpointPath
   *   The endpoint path with leading slash, e.g. '/subscribe'.
   *   It does not contain the /api/v1 prefix.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request object.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   */
  public function __construct(
    private readonly string $endpointPath,
    private readonly RequestInterface $request,
    private readonly ResponseInterface $response,
  ) {}

  /**
   * Gets the endpoint path.
   */
  public function getEndpointPath(): string {
    return $this->endpointPath;
  }

  /**
   * Gets the request.
   */
  public function getRequest(): RequestInterface {
    return $this->request;
  }

  /**
   * Gets the response.
   */
  public function getResponse(): ResponseInterface {
    return $this->response;
  }

  /**
   * Gets the HTTP status code from the response.
   */
  public function getStatusCode(): int {
    return $this->response->getStatusCode();
  }

  /**
   * Gets the response body as string.
   */
  public function getResponseBody(): string {
    // Unlike ->getContents(), ->__toString() is idempotent.
    return $this->body ??= $this->response->getBody()->__toString();
  }

  /**
   * Throws an exception if the response code is not 200.
   *
   * @return $this
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\FailureResponseException
   *   The respons  code is different from 200.
   */
  public function assertSuccess(): static {
    $code = $this->getStatusCode();
    if ($code == 200) {
      return $this;
    }
    try {
      $message = $this->getJsonString();
      $previous_exception = NULL;
    }
    catch (MalformedResponseException $e) {
      $message = $this->getResponseBody();
      $previous_exception = $e;
    }
    $message = $code . ' ' . $message;
    if ($code == 404) {
      return throw new NotFoundException(
        $message,
        $this->request,
        $this->response,
        $this->getResponseBody(),
        $previous_exception,
      );
    }
    return throw new FailureResponseException(
      $message,
      $this->request,
      $this->response,
      $this->getResponseBody(),
      $previous_exception,
    );
  }

  /**
   * Throws an exception if no json header is present in the response.
   *
   * @return $this
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   The json header is missing.
   */
  public function assertJsonHeader(): static {
    $content_type_header = $this->response->getHeaderLine('content-type');
    if ($content_type_header !== 'application/json') {
      $this->fail(sprintf(
        "Expected 'content-type' header 'application/json', found %s.",
        var_export($content_type_header, TRUE),
      ));
    }
    return $this;
  }

  /**
   * Gets a processed version of the response data.
   *
   * @param callable(string|array): T $transform
   *   Transformation to apply to the response data.
   *   The transformation may throw any type of exception for unexpected data.
   *
   * @template T
   *
   * @return T
   *   The return value from the transformation.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   One of:
   *   - The response is missing the json header.
   *   - The response does not contain valid json.
   *   - The json data is something other than string|array.
   *   - An exception occured in the callback.
   */
  public function map(callable $transform): mixed {
    $data = $this->getJsonData();
    try {
      return $transform($data);
    }
    catch (ApiException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $this->fail(sprintf(
        "Unexpected JSON data: %s.\nOriginal data:\n%s",
        $e->getMessage(),
        Yaml::encode($data),
      ), $e);
    }
  }

  /**
   * Gets the json data if it is an array.
   *
   * @return array
   *   Parsed json data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   One of:
   *   - The response is missing the json header.
   *   - The response does not contain valid json.
   *   - The json data is not an array.
   */
  public function getJsonArray(): array {
    $data = $this->getJsonData();
    if (!is_array($data)) {
      $this->fail(sprintf('Expected JSON data to be an array. Found:\n%s.', Yaml::encode($data)));
    }
    return $data;
  }

  /**
   * Gets json response data, and asserts it to be a string.
   *
   * @return string
   *   The json response data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   One of:
   *   - The response is missing the json header.
   *   - The response does not contain valid json.
   *   - The json data is not a string.
   */
  public function getJsonString(): string {
    $data = $this->getJsonData();
    if (!is_string($data)) {
      $this->fail(sprintf('Expected JSON data to be a string. Found:\n%s.', Yaml::encode($data)));
    }
    return $data;
  }

  /**
   * Gets parsed json response data.
   *
   * @return string|array
   *   The json response data.
   *   For all known endpoints, this can be string or array.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   The response does not contain valid json, or is missing the json header.
   */
  public function getJsonData(): string|array {
    if ($this->data !== NULL) {
      return $this->data;
    }
    $this->assertJsonHeader();
    $body = $this->getResponseBody();
    if ($body === '') {
      $this->fail('Expected non-empty JSON response body.');
    }
    try {
      // Drupal's Json::encode() is not consistent across Drupal versions.
      $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      $this->fail(sprintf(
        "Invalid JSON: %s.\nMessage: %s",
        $body,
        $e->getMessage(),
      ));
    }
    if (!is_array($data) && !is_string($data)) {
      $this->fail(sprintf(
        "Expected JSON response data to be string or array. Found: %s",
        Yaml::encode($data),
      ));
    }
    return $this->data = $data;
  }

  /**
   * Throws an exception with request and response.
   *
   * @param string $message
   *   The message, which will be enhanced with information about request and
   *   response.
   * @param \Throwable|null $previous
   *   A previous exception to wrap.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   The exception.
   */
  public function fail(string $message, ?\Throwable $previous = NULL): never {
    $message = $this->buildFailureMessage($message);
    throw new MalformedResponseException($message, $this->request, $this->response, $previous);
  }

  /**
   * Enhances a message for exceptions.
   *
   * @param string $message
   *   The original message.
   *
   * @return string
   *   The enhanced message with additional information.
   */
  protected function buildFailureMessage(string $message): string {
    return sprintf(
      "%s\nRequest: %s %s\nResponse code: %s",
      $message,
      $this->request->getMethod(),
      $this->endpointPath,
      $this->response->getStatusCode(),
    );
  }

}
