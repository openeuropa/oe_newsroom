<?php

namespace Drupal\oe_newsroom_vcr\DataMapper;

use Drupal\oe_newsroom_vcr\Helper\ArrayHelper;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Contains methods to transcode a response object to a yaml record.
 *
 * This is bi-directional, the record will be converted back to a response
 * later. However, that conversion is allowed to lose or simplify some data.
 *
 * @internal
 */
class ResponseMapper {

  /**
   * Exports a response to a yaml array.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request that this response is for..
   *
   * @return array
   *   The record suitable for yaml storage.
   */
  public function exportAndSimplifyResponse(ResponseInterface $response, RequestInterface $request): array {
    $record = [
      'status' => $response->getStatusCode(),
      'headers' => $response->getHeaders(),
      'body' => $response->getBody()->__toString(),
    ];
    if (
      $request->getUri()->getHost() !== 'ec.europa.eu' ||
      !str_starts_with($request->getUri()->getPath(), '/newsroom/api/v1/')
    ) {
      // Do not further simplify non-Newsroom responses.
      return $record;
    }
    try {
      $record['data'] = json_decode($record['body'], TRUE, 100, JSON_THROW_ON_ERROR);
      unset($record['body']);
    }
    catch (\JsonException $e) {
      $record['json.exception'] = $e->getMessage();
    }
    // Remove headers with default values.
    $record['headers'] = ArrayHelper::arrayDiffAssocStrict($record['headers'], [
      'Content-Type' => ['application/json'],
      'Cache-Control' => ['no-cache, private'],
    ]);
    // Remove headers that are not relevant.
    $record['headers'] = array_diff_key($record['headers'], array_fill_keys([
      'Connection',
      'Cache-Control',
      'Date',
      'Server-Timing',
      'Server',
      'Set-Cookie',
      'Transfer-Encoding',
      'Vary',
      'X-Cnection',
      'X-RateLimit-Limit',
      'X-RateLimit-Remaining',
    ], TRUE));
    // Remove default values from the record.
    $record = ArrayHelper::arrayDiffAssocStrict($record, [
      'status' => 200,
      'headers' => [],
    ]);
    return $record;
  }

  /**
   * Imports a yaml array to a response object.
   *
   * @param array $record
   *   The record from a yaml file.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response object.
   */
  public function importResponseRecord(array $record): ResponseInterface {
    $record += [
      'status' => 200,
      'headers' => [],
    ];
    $record['headers'] += [
      'Content-Type' => 'application/json',
    ];
    $body = $record['body'] ?? json_encode(
      $record['data'] ?? throw new \RuntimeException("Expected either 'body' or 'data' to be set."),
      JSON_THROW_ON_ERROR,
    );
    return new Response(
      $record['status'],
      $record['headers'],
      $body,
    );
  }

}
