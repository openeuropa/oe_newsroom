<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Json;
use Drupal\oe_newsroom\Exception\Api\ApiRequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
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
   * Performs a GET request.
   *
   * @param string $endpoint_path
   *   The endpoint path, without leading backslash.
   * @param array<string, mixed> $query
   *   Request query parameters.
   * @param array<string> $signature_input
   *   String parts to join when generating the signature token.
   * @param list<string|int>|null $signature_keys_to_normalize
   *   Signature keys that should be normalized with mb_strtolower() depending
   *   on a setting, or NULL o normalize all signature keys.
   * @param bool $assert_success
   *   TRUE to throw exception on non-200 response code.
   *   FALSE to return all responses.
   *
   * @return \Drupal\oe_newsroom\Api\ApiResponse
   *   An API response object.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request failed.
   */
  public function get(
    string $endpoint_path,
    array $query,
    array $signature_input,
    ?array $signature_keys_to_normalize = NULL,
    bool $assert_success = TRUE,
  ): ApiResponse {
    $query['key'] = $this->generateComposedKey($signature_input, $signature_keys_to_normalize);
    $query['app'] = $this->connection->appId;
    $endpoint_url = $this->connection->url . '/' . $endpoint_path;
    $uri = $this->uriFactory->createUri($endpoint_url)
      ->withQuery(http_build_query($query));
    $request = $this->requestFactory->createRequest('GET', $uri);
    return $this->sendRequest($request, $endpoint_path, $assert_success);
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
   * @param list<string|int>|null $signature_keys_to_normalize
   *   Signature keys that should be normalized with mb_strtolower() depending
   *   on a setting, or NULL o normalize all signature keys.
   * @param bool $assert_success
   *   TRUE to throw exception on non-200 response code.
   *   FALSE to return all responses.
   *
   * @return \Drupal\oe_newsroom\Api\ApiResponse
   *   An API response object.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The request failed.
   *
   * @todo Remove the $pass_app_id parameter when the endpoints have been
   *   standardized.
   */
  public function post(
    string $endpoint_path,
    array $payload,
    array $signature_input,
    ?array $signature_keys_to_normalize = NULL,
    bool $assert_success = TRUE,
  ): ApiResponse {
    $signature = $this->generateComposedKey($signature_input, $signature_keys_to_normalize);
    if ($signature !== NULL) {
      $payload['key'] = $signature;
    }
    $payload['app'] = $this->connection->appId;
    $json = Json::encode($payload);
    $body = $this->streamFactory->createStream($json);
    $endpoint_url = $this->connection->url . '/' . $endpoint_path;
    $uri = $this->uriFactory->createUri($endpoint_url);
    $request = $this->requestFactory->createRequest('POST', $uri)
      ->withHeader('Content-Type', 'application/json')
      ->withBody($body);
    return $this->sendRequest($request, $endpoint_path, $assert_success);
  }

  /**
   * Sends a request, and wraps the exception.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param string $endpoint_path
   *   The endpoint path, used in exception messages.
   * @param bool $assert_success
   *   TRUE to throw exception on non-200 response code.
   *   FALSE to return all responses.
   *
   * @return \Drupal\oe_newsroom\Api\ApiResponse
   *   An API response object.
   *   If $assert_success was chosen, the response code will be 200.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\FailureResponseException
   *   The response code is different from 200, and $assert_success was chosen.
   */
  protected function sendRequest(
    RequestInterface $request,
    string $endpoint_path,
    bool $assert_success,
  ): ApiResponse {
    try {
      $response = $this->httpClient->sendRequest($request);
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
    $api_response = new ApiResponse($endpoint_path, $request, $response);
    if ($assert_success) {
      $api_response->assertSuccess();
    }
    return $api_response;
  }

  /**
   * Generates a multiple parameters key.
   *
   * @param array<string> $signature_input
   *   The parameters to be used for the key.
   * @param list<string|int>|null $signature_keys_to_normalize
   *   Signature keys that should be normalized with mb_strtolower() depending
   *   on a setting, or NULL o normalize all signature keys.
   *
   * @return string
   *   Generated communication key.
   */
  public function generateComposedKey(
    array $signature_input,
    array|null $signature_keys_to_normalize = [],
  ): string {
    if (!$this->connection->normalised) {
      $signature_input_str = implode($signature_input);
    }
    elseif ($signature_keys_to_normalize === NULL) {
      $signature_input_str = mb_strtolower(implode($signature_input));
    }
    else {
      foreach ($signature_keys_to_normalize as $key) {
        if (isset($signature_input[$key])) {
          $signature_input[$key] = mb_strtolower($signature_input[$key]);
        }
      }
      $signature_input_str = implode($signature_input);
    }
    return hash($this->connection->hashMethod, $signature_input_str . $this->connection->privateKey);
  }

}
