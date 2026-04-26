<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Json;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Api\ApiResponseException;
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
    try {
      $response = $this->httpClient->sendRequest($request);
    }
    catch (ClientExceptionInterface $e) {
      // @todo Handle different cases.
      // @todo Does anything need to be sanitized for security?
      throw new ApiException(sprintf(
        'A GET request to %s failed with %s.',
        $this->connection->url . '/' . $endpoint_path,
        $e->getCode(),
      ), previous: $e);
    }
    return $this->extractJsonFromResponse($response, $request, 'GET', $endpoint_url);
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
    $payload['key'] = $this->generateComposedKey($signature_input);
    if ($pass_app_id) {
      $payload['app'] = $this->connection->appId;
    }
    $json = Json::encode($payload);
    $body = $this->streamFactory->createStream($json);
    $endpoint_url = $this->connection->url . '/' . $endpoint_path;
    $uri = $this->uriFactory->createUri($endpoint_url);
    $request = $this->requestFactory->createRequest('POST', $uri)
      ->withHeader('Content-Type', 'application/json')
      ->withBody($body);
    try {
      $response = $this->httpClient->sendRequest($request);
    }
    catch (ClientExceptionInterface $e) {
      // @todo Handle different cases.
      // @todo Does anything need to be sanitized for security?
      throw new ApiException(sprintf(
        'A POST request to %s failed with %s.',
        $endpoint_url,
        $e->getCode(),
      ), previous: $e);
    }
    return $this->extractJsonFromResponse($response, $request, 'POST', $endpoint_url);
  }

  /**
   * Parses a json response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param string $method
   *   The request method, used in exception messages.
   * @param string $url
   *   The request url, used in exception messages.
   *
   * @return array|string
   *   Parsed json data.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation was denied or failed.
   */
  protected function extractJsonFromResponse(ResponseInterface $response, RequestInterface $request, string $method, string $url): array|string {
    if ($response->getStatusCode() !== 200) {
      throw new ApiResponseException(  sprintf(
        'A %s request to %s returned status code %s.',
        $method,
        var_export($url, TRUE),
        $response->getStatusCode(),
      ), $request, $response, $response->getStatusCode());
    }
    // Unlike ->getContents(), ->__toString() is idempotent.
    $json = $response->getBody()->__toString();
    // @todo Is a response from a POST request always json format?
    try {
      // Drupal's Json::encode() is not consistent across Drupal versions.
      $data = json_decode($json, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new ApiException(sprintf(
        'A %s request to %s returned invalid json %s.',
        $method,
        var_export($url, TRUE),
        var_export($json, TRUE),
      ), previous: $e);
    }
    if (!is_array($data) && !is_string($data)) {
      throw new ApiException(sprintf(
        'A %s request to %s returned unexpected json data %s.',
        $method,
        var_export($url, TRUE),
        var_export($data, TRUE),
      ));
    }
    return $data;
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
  protected function generateComposedKey(array $signature_input): string {
    return hash($this->connection->hashMethod, implode($signature_input) . $this->connection->privateKey);
  }

}
