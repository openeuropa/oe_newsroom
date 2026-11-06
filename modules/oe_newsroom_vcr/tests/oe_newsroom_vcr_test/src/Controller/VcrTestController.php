<?php

namespace Drupal\oe_newsroom_vcr_test\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
   * Makes an test request returning a page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The page content render array.
   */
  public function page(Request $request): array {
    $url = (string) Url::fromRoute('oe_newsroom_vcr_test.api')
      ->setAbsolute()
      ->toString();
    $response = $this->client->request('GET', $url);
    $body = $response->getBody()->__toString();
    $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
    return [
      '#markup' => new FormattableMarkup('<pre id="test-content">@content</pre>', [
        '@content' => Yaml::encode($data),
      ]),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Makes a test request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function api(Request $request): JsonResponse {
    return new JsonResponse([
      'animals' => [
        'cat',
        'penguin',
      ],
    ]);
  }

}
