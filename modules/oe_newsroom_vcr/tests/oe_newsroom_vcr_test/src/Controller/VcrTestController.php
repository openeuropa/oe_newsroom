<?php

namespace Drupal\oe_newsroom_vcr_test\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Test controller for the VCR.
 *
 * @see \Drupal\Tests\oe_newsroom_vcr\Functional\NewsroomVcrFunctionalTest
 */
class VcrTestController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly ClientInterface $client,
  ) {}

  /**
   * Builds a page with content fetched from an API url.
   *
   * @return array
   *   The page content render array.
   *   This contains data fetched from the API url.
   */
  public function page(): array {
    $data = $this->fetchApiData();
    return [
      '#markup' => new FormattableMarkup('<pre id="test-content">@content</pre>', [
        '@content' => Yaml::encode($data),
      ]),
      // Do not cache the page, so that every new page request starts a new
      // outgoing request.
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Fetches data from the API url.
   *
   * @return mixed
   *   Parsed json response data.
   */
  protected function fetchApiData(): mixed {
    $url = (string) Url::fromRoute('oe_newsroom_vcr_test.api')
      ->setAbsolute()
      ->toString();
    // Perform the outgoing http request.
    // If the VCR is in recording mode, this will make an actual request, the
    // request and response will be recorded, and the real response returned.
    // If the VCR is in replay mode, this will not make the actual request, and
    // instead return pre-recorded data.
    $response = $this->client->request('GET', $url);
    $body = $response->getBody()->__toString();
    return json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Builds a response for an API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   *   This always contains the same json data.
   */
  public function api(): JsonResponse {
    return new JsonResponse([
      'animals' => [
        'cat',
        'penguin',
      ],
    ]);
  }

}
