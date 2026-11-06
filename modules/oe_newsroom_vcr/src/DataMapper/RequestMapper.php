<?php

namespace Drupal\oe_newsroom_vcr\DataMapper;

use Drupal\oe_newsroom_vcr\Helper\ArrayDefaultValuesHelper;
use Drupal\oe_newsroom_vcr\Helper\ArrayHelper;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains a method to simplify and serialize a request for yaml.
 *
 * This is one-directional, the record will never be converted back to a request
 * object.
 *
 * @internal
 */
class RequestMapper {

  /**
   * Serializes a request as a TaggedValue record suitable for yaml recording.
   *
   * Values that are the same as for a default request are removed, to keep the
   * recording simple.
   *
   * If the request is a Newsroom API request, more defaults are removed, and
   * the tag name will be NewsroomRequest.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return \Symfony\Component\Yaml\Tag\TaggedValue
   *   The record.
   */
  public function packAndSimplifyRequest(RequestInterface $request): TaggedValue {
    $record = $this->doPackAndSimplifyRequest($request);
    if (($record['host'] ?? NULL) !== 'ec.europa.eu' || !str_starts_with($record['path'] ?? NULL, '/newsroom/api/v1/')) {
      return new TaggedValue('Request', $record);
    }
    $newsroom_default_request = new Request('GET', 'https://ec.europa.eu/xyz');
    $newsroom_defaults = $this->doPackAndSimplifyRequest($newsroom_default_request);
    unset($newsroom_defaults['path']);
    $newsroom_reduced_record = ArrayDefaultValuesHelper::arrayRemoveDefaultsRecursive($record, $newsroom_defaults, new TaggedValue('Missing', NULL));
    return new TaggedValue('NewsroomRequest', $newsroom_reduced_record);
  }

  /**
   * Exports a request as array, removing generic defaults.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return array
   *   An array with request values.
   */
  public function doPackAndSimplifyRequest(RequestInterface $request): array {
    $record = $this->doPackRequest($request);
    $host_with_port = $record['host'];
    if ($record['port'] === 80) {
      unset($record['port']);
    }
    else {
      $host_with_port .= ':' . $record['port'];
    }
    $default_request = new Request('GET', "https://$host_with_port/xyz");
    $defaults = $this->doPackRequest($default_request);
    unset($defaults['host'], $defaults['path']);
    if ($record['port'] !== 80) {
      unset($defaults['port']);
    }
    return ArrayDefaultValuesHelper::arrayRemoveDefaultsRecursive($record, $defaults, new TaggedValue('Missing', NULL));
  }

  /**
   * Exports a request as array.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request object.
   *
   * @return array
   *   The exported values.
   */
  private function doPackRequest(RequestInterface $request): array {
    $uri = $request->getUri();
    $record = [
      'method' => $request->getMethod(),
      'scheme' => $uri->getScheme(),
      'host' => $uri->getHost(),
      'port' => $uri->getPort(),
      'path' => $uri->getPath(),
      'query' => $uri->getQuery(),
      'fragment' => $uri->getFragment(),
      'authority' => $uri->getAuthority(),
      'user_info' => $uri->getUserInfo(),
      'headers' => $request->getHeaders(),
    ];
    // Remove default values.
    $record = ArrayHelper::arrayDiffAssocStrict($record, [
      'method' => 'GET',
      'scheme' => 'https',
      'port' => '80',
    ]);
    // Parse the query as array.
    if ($record['query'] !== '') {
      parse_str($record['query'], $record['query']);
    }
    else {
      unset($record['query']);
    }
    // Parse the request body as json, if possible.
    $body = $request->getBody()->__toString();
    if ($body !== '') {
      $record['body'] = $body;
      if (($record['headers']['Content-Type'] ?? NULL) === ['application/json']) {
        unset($record['headers']['Content-Type']);
        try {
          $record['data'] = json_decode($body, TRUE, 100, JSON_THROW_ON_ERROR);
          unset($record['body']);
        }
        catch (\JsonException $e) {
          $record['json.exception'] = $e->getMessage();
        }
      }
    }
    // Remove noisy headers.
    unset($record['headers']['User-Agent']);
    unset($record['headers']['Content-Length']);
    // Remove header array if empty.
    if (($record['headers'] ?? NULL) === []) {
      unset($record['headers']);
    }
    return $record;
  }

}
