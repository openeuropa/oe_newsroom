<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_vcr;

use Drupal\Component\Serialization\Yaml;
use Drupal\oe_newsroom_vcr\DataMapper\RequestMapper;
use Drupal\oe_newsroom_vcr\DataMapper\ResponseMapper;
use Drupal\oe_newsroom_vcr\Helper\ArrayHelper;
use Drupal\oe_newsroom_vcr\Vcr\VcrMode;
use Drupal\oe_newsroom_vcr\Vcr\VcrRuntimeInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Middleware that intercepts the outgoing HTTP request.
 */
class ClientMiddleware {

  public function __construct(
    protected readonly VcrRuntimeInterface $vcr,
    protected readonly RequestMapper $requestMapper,
    protected readonly ResponseMapper $responseMapper,
  ) {}

  /**
   * Returns a callback to handle the outgoing HTTP request.
   *
   * @return callable(callable(\Psr\Http\Message\RequestInterface, array): \GuzzleHttp\Promise\PromiseInterface): (callable(\Psr\Http\Message\RequestInterface, array): \GuzzleHttp\Promise\PromiseInterface)
   *   The middleware callback, which wraps a request handler callback.
   */
  public function __invoke(): callable {
    return function (callable $handler): callable {
      return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
        return $this->handle($request, $options, $handler);
      };
    };
  }

  /**
   * Handles the incoming HTTP request using a decorated handler.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request to be processed.
   * @param array $options
   *   An associative array of options for processing the request.
   * @param callable(\Psr\Http\Message\RequestInterface, array): \GuzzleHttp\Promise\PromiseInterface $decorated_handler
   *   The decorated request callback.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise representing the asynchronous result of the processed request.
   */
  protected function handle(RequestInterface $request, array $options, callable $decorated_handler): PromiseInterface {
    // Do not collect requests that are part of a test.
    if ($request->getHeader('user-agent') === ['Symfony BrowserKit']) {
      return $decorated_handler($request, $options);
    }

    $mode = $this->vcr->getMode();
    if ($mode === VcrMode::Poisoned) {
      return $this->handleFailure("The vcr is poisoned.");
    }

    $request_failure = $this->writeOrVerifyRequest($request, $mode);
    if ($request_failure !== NULL) {
      return $this->fail($request_failure);
    }

    $promise = match ($mode) {
      VcrMode::Replay => $this->loadReplayPromise(),
      VcrMode::Passthrough,
      VcrMode::Recording => $decorated_handler($request, $options),
    };

    if ($mode === VcrMode::Recording) {
      $promise = $promise->then(
        function (ResponseInterface $response) use ($request): ResponseInterface {
          $this->vcr->addRecord(new TaggedValue(
            'Response',
            $this->responseMapper->exportAndSimplifyResponse($response, $request),
          ));
          return $response;
        },
      );
    }

    return $promise;
  }

  /**
   * Handles a request when in replay mode.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise representing the asynchronous result of the processed request.
   */
  protected function loadReplayPromise(): PromiseInterface {
    $record = $this->vcr->readNextRecord($position);
    if (!$record instanceof TaggedValue || $record->getTag() !== 'Response') {
      return $this->fail(sprintf(
        "Expected a recorded response at position %s. Found:\n%s",
        $position,
        Yaml::encode($record),
      ));
    }
    $response = $this->responseMapper->importResponseRecord($record->getValue());
    return Create::promiseFor($response);
  }

  /**
   * Adds or asserts a request with the VCR.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param \Drupal\oe_newsroom_vcr\Vcr\VcrMode $mode
   *   The VCR mode.
   *
   * @return string|null
   *   A problem string, or NULL on success.
   */
  protected function writeOrVerifyRequest(RequestInterface $request, VcrMode $mode): ?string {
    if ($mode === VcrMode::Passthrough || $mode === VcrMode::Poisoned) {
      return NULL;
    }
    $actual_record = $this->requestMapper->packAndSimplifyRequest($request);
    if ($mode === VcrMode::Recording) {
      $this->vcr->addRecord($actual_record);
      return NULL;
    }
    $replay_record = $this->vcr->readNextRecord($position);
    if (!$replay_record || $replay_record->getTag() !== $actual_record->getTag()) {
      return "Request tag does not match recording at position $position.\n" . Yaml::encode([
        'expected' => $replay_record,
        'actual' => $actual_record,
      ]);
    }
    assert($mode === VcrMode::Replay);
    $expected = $replay_record->getValue();
    $actual = $actual_record->getValue();
    $expected_sorted = ArrayHelper::ksortRecursive($expected);
    $actual_sorted = ArrayHelper::ksortRecursive($actual);
    // Comparing yaml is more strict than ->assertEquals(), but still allows
    // different object identity for TaggedValue instances.
    if (Yaml::encode($expected_sorted) !== Yaml::encode($actual_sorted)) {
      return "Request does not match recording at position $position.\n" . Yaml::encode([
        'expected' => $expected,
        'actual' => $actual,
      ]);
    }
    return NULL;
  }

  /**
   * Reports a failure to the VCR, and creates a promise for a failure response.
   *
   * @param string $failure
   *   The failure message.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise for a failure response.
   */
  protected function fail(string $failure): PromiseInterface {
    $this->vcr->setFailure($failure);
    return $this->handleFailure($failure);
  }

  /**
   * Creates a promise for a failure response.
   *
   * @param string $failure
   *   The failure message.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise for a failure response.
   */
  protected function handleFailure(string $failure): PromiseInterface {
    return Create::promiseFor(new Response(
      500,
      ['Content-Type' => 'application/json'],
      json_encode([
        'error' => 'VCR failure',
        'failure' => $failure,
      ], JSON_THROW_ON_ERROR),
    ));
  }

}
