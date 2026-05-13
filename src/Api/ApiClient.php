<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\oe_newsroom\Exception\Api\ApiRequestException;
use Drupal\oe_newsroom\Exception\Api\BadRequestException;
use Drupal\oe_newsroom\Exception\Api\FailureResponseException;
use Drupal\oe_newsroom\Exception\Api\MalformedResponseException;
use Drupal\oe_newsroom\Exception\Api\NotFoundException;
use Drupal\oe_newsroom\Exception\Api\UnauthorizedException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This is a convenience wrapper around the http client.
 *
 * It also provides getters for some connection settings, to help reduce the
 * constructor signature of endpoint classes.
 */
class ApiClient {

  public function __construct(
    protected readonly ClientInterface $httpClient,
    #[Autowire(service: 'psr17.server_request_factory')]
    protected readonly RequestFactoryInterface $requestFactory,
    #[Autowire(service: 'psr17.server_request_factory')]
    protected readonly UriFactoryInterface $uriFactory,
    #[Autowire(service: 'psr17.stream_factory')]
    protected readonly StreamFactoryInterface $streamFactory,
    protected readonly NewsroomConnection $connection,
  ) {}

  /**
   * Gets the service id for the node service.
   *
   * @return string
   *   Service id for the node service.
   *   Some endpoints expect this as 'sv_id' parameter.
   */
  public function getNodeServiceId(): string {
    return $this->connection->nodeServiceId;
  }

  /**
   * Gets the app id setting for non-standard endpoints.
   *
   * This would be automatically passed as 'app' in request parameters, but some
   * endpoints need it passed under a different key.
   *
   * @return string
   *   The app id setting.
   *
   * @todo Remove this method when the web service has been standardized.
   */
  public function getAppId(): string {
    return $this->connection->appId;
  }

  /**
   * Gets the universe acronym for non-standard endpoints.
   *
   * This is not needed in most endpoints, because it would be redundant given
   * there is app id and key. We only provide it to support non-standard
   * endpoints.
   *
   * @return string
   *   The universe acronym.
   *
   * @todo Remove this method when the web service has been standardized.
   */
  public function getUniverseAcronym(): string {
    return $this->connection->universe;
  }

  /**
   * Normalizes an email address.
   *
   * @param string $email
   *   Original email address.
   *
   * @return string
   *   Normalized email address.
   *   If normalization is enabled, this will be lowercase.
   *
   * @todo Review if this is the right place for this.
   */
  public function normalizeEmail(string $email): string {
    return $this->connection->normalised ? mb_strtolower($email) : $email;
  }

  /**
   * Performs a GET request to fetch JSON data.
   *
   * @param string $endpoint_path
   *   The endpoint path, without leading backslash.
   * @param array<string, mixed> $query
   *   Request query parameters.
   * @param array<string> $signature_input
   *   String parts to join when generating the signature token.
   *
   * @return array|string
   *   Parsed json response data.
   *   In all known cases this is a string or array.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request failed.
   */
  public function fetchJson(string $endpoint_path, array $query, array $signature_input): array|string {
    $query['key'] = $this->generateComposedKey($signature_input);
    $query['app'] = $this->connection->appId;
    $endpoint_url = $this->connection->url . '/' . $endpoint_path;
    $uri = $this->uriFactory->createUri($endpoint_url)
      ->withQuery(http_build_query($query));
    $request = $this->requestFactory->createRequest('GET', $uri);
    $response = $this->sendRequest($request, $endpoint_path);
    return $this->processResponse($response, $request, $endpoint_url);
  }

  /**
   * Performs a POST request with JSON payload.
   *
   * @param string $endpoint_path
   *   Endpoint path.
   * @param array<string, mixed> $payload
   *   The payload.
   * @param array<string> $signature_input
   *   Values that will be part of the signature key.
   * @param bool $pass_app_id
   *   TRUE to pass an 'app' parameter with the app id.
   *   FALSE to not pass this parameter. This is to support endpoints with a
   *   non-standard signature.
   *
   * @return array|string
   *   The full response json data.
   *   In some cases this is a string instead of an array.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request was denied or failed.
   *
   * @todo Remove the $pass_app_id parameter when the endpoints have been
   *   standardized.
   */
  public function postJson(string $endpoint_path, array $payload, array $signature_input, bool $pass_app_id = TRUE): array|string {
    $request = $this->createPostRequest($endpoint_path, $payload, $signature_input, $pass_app_id);
    $response = $this->sendRequest($request, $endpoint_path);
    return $this->processResponse($response, $request, $endpoint_path);
  }

  /**
   * Creates a POST request object.
   *
   * @param string $endpoint_path
   *   The endpoint path.
   * @param array $payload
   *   The POST payload.
   * @param array<string> $signature_input
   *   Values that will be part of the signature key.
   * @param bool $pass_app_id
   *   TRUE to pass an 'app' parameter with the app id.
   *   FALSE to not pass this parameter. This is to support endpoints with a
   *   non-standard signature.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request object.
   */
  protected function createPostRequest(string $endpoint_path, array $payload, array $signature_input, bool $pass_app_id = TRUE): RequestInterface {
    $payload['key'] = $this->generateComposedKey($signature_input);
    if ($pass_app_id) {
      $payload['app'] = $this->connection->appId;
    }
    $json = Json::encode($payload);
    $body = $this->streamFactory->createStream($json);
    $endpoint_url = $this->connection->url . '/' . $endpoint_path;
    $uri = $this->uriFactory->createUri($endpoint_url);
    return $this->requestFactory->createRequest('POST', $uri)
      ->withHeader('Content-Type', 'application/json')
      ->withBody($body);
  }

  /**
   * Sends a request, and wraps the exception.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param string $endpoint_path
   *   The endpoint path, used in exception messages.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiRequestException
   *   A ClientException was thrown by the http client.
   */
  protected function sendRequest(RequestInterface $request, string $endpoint_path): ResponseInterface {
    try {
      return $this->httpClient->sendRequest($request);
    }
    catch (ClientExceptionInterface $e) {
      // @todo Does anything need to be sanitized for security?
      throw new ApiRequestException(sprintf(
        'A %s request to %s failed with %s.',
        $request->getMethod(),
        $endpoint_path,
        $e->getCode(),
      ), $request, $e);
    }
  }

  /**
   * Processes a response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request, used in exceptions and messages.
   * @param string $endpoint_path
   *   The endpoint path, used in exception messages.
   *   This is not the full url, to not reveal details.
   *
   * @return array|string
   *   Parsed json data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation was denied or failed.
   */
  public function processResponse(ResponseInterface $response, RequestInterface $request, string $endpoint_path): array|string {
    $create_exception = fn (string $message_part, ?\Throwable $previous = NULL): MalformedResponseException => new MalformedResponseException(sprintf(
      "A %s request to %s returned a %s response with %s.\n%s",
      $request->getMethod(),
      var_export($endpoint_path, TRUE),
      $response->getStatusCode(),
      $message_part,
      Yaml::encode([
        'path' => $endpoint_path,
        'status' => $response->getStatusCode(),
        'body' => $response->getBody()->getContents(),
        'headers' => $response->getHeaders(),
      ]),
    ), $request, $response, $previous);

    $this->checkResponseJsonHeader($response, $create_exception);
    $data = $this->readResponseJson($response, $create_exception);

    if ($response->getStatusCode() === 200) {
      // Successful responses should return array or string.
      if (!is_array($data) && !is_string($data)) {
        throw $create_exception(sprintf(
          'unexpected json data %s.',
          var_export($data, TRUE),
        ));
      }
      return $data;
    }

    if (!is_string($data)) {
      // Failure responses should have a json-encoded string message as body.
      throw $create_exception(sprintf(
        'unexpected json data %s.',
        var_export($data, TRUE),
      ));
    }

    switch ($response->getStatusCode()) {
      case 404:
        // @todo Distinguish different cases of "Not found".
        throw new NotFoundException($data, $request, $response, $data);

      case 400:
        throw new BadRequestException($data, $request, $response, $data);

      case 401:
        throw new UnauthorizedException($data, $request, $response, $data);

      default:
        throw new FailureResponseException($data, $request, $response, $data);
    }
  }

  /**
   * Reads json data from the response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response from Newsroom API.
   * @param \Closure(string, ?\Throwable): MalformedResponseException $create_exception
   *   A callback that will create an exception.
   *
   * @return mixed
   *   Data parsed from json.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   The response has invalid json.
   */
  protected function readResponseJson(ResponseInterface $response, \Closure $create_exception): mixed {
    // Unlike ->getContents(), ->__toString() is idempotent.
    $body = $response->getBody()->__toString();
    if ($body === '') {
      throw $create_exception('empty body in ' . var_export($response, TRUE));
    }
    try {
      // Drupal's Json::encode() is not consistent across Drupal versions.
      $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw $create_exception(sprintf(
        'invalid json %s.',
        var_export($body, TRUE),
      ), $e);
    }
    return $data;
  }

  /**
   * Checks that a response content type header says it's json.
   *
   * This is expected for all responses from Newsroom, even failure respones.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response from Newsroom API.
   * @param \Closure(string, ?\Throwable): MalformedResponseException $create_exception
   *   A callback that will create an exception.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\MalformedResponseException
   *   The response has an unexpected content-type header.
   */
  protected function checkResponseJsonHeader(ResponseInterface $response, \Closure $create_exception): void {
    if ($response->getHeaderLine('content-type') !== 'application/json') {
      throw $create_exception(sprintf(
        'content-type header %s',
        var_export($response->getHeaderLine('content-type'), TRUE),
      ));
    }
  }

  /**
   * Generates a multiple parameters key.
   *
   * @param array<string> $signature_input
   *   The parameters to be used for the key.
   *
   * @return string
   *   Generated communication key.
   */
  public function generateComposedKey(array $signature_input): string {
    return hash($this->connection->hashMethod, implode($signature_input) . $this->connection->privateKey);
  }

}
